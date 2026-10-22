<?php

namespace Tests\Feature;

use App\Actions\Auth\VerifyAccount;
use App\Models\AccountAccess;
use App\Models\User;
use App\Notifications\AccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $extra = []): array
    {
        return array_merge(['name' => 'Synthetic Photographer', 'email' => 'PHOTO@example.test',
            'password' => 'SyntheticPassword123', 'password_confirmation' => 'SyntheticPassword123'], $extra);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
    }

    public function test_registration_rejects_privilege_injection_and_starts_no_trial_before_verification(): void
    {
        Notification::fake();
        $this->post('/register', $this->registration(['role' => 'admin', 'email_verified_at' => now(), 'suspended_at' => now(), 'access_ends_at' => now()->addYears(10)]))
            ->assertRedirect('/email/verify');
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('photo@example.test', $user->email);
        $this->assertSame('photographer', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->suspended_at);
        $this->assertDatabaseCount('account_access', 0);
        $this->assertTrue(Hash::check('SyntheticPassword123', $user->password));
        Notification::assertSentTo($user, AccountLink::class);
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->get('/admin')->assertRedirect('/email/verify');
    }

    public function test_duplicate_case_email_and_weak_password_are_rejected(): void
    {
        User::factory()->create(['email' => 'photo@example.test']);
        $this->post('/register', $this->registration())->assertSessionHasErrors('email');
        $this->post('/register', $this->registration(['email' => 'new@example.test', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_validation_prevents_bcrypt_truncation_and_null_byte_errors(): void
    {
        foreach ([str_repeat('a', 72).'1', str_repeat('é', 36).'1', "SyntheticPassword123\0"] as $index => $password) {
            $this->post('/register', $this->registration(['email' => 'long'.$index.'@example.test', 'password' => $password, 'password_confirmation' => $password]))->assertSessionHasErrors('password');
        }
        $this->assertDatabaseCount('users', 0);
        $this->post('/login', ['email' => 'absent@example.test', 'password' => str_repeat('a', 73)])->assertSessionHasErrors('password');
    }

    public function test_signed_verification_starts_exactly_one_trial_and_reset_login_reverification_do_not_restart_it(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $this->actingAs($user)->get($url)->assertRedirect('/dashboard');
        $access = AccountAccess::findOrFail($user->id);
        $this->assertTrue($access->trial_started_at->equalTo(now()));
        $this->assertTrue($access->trial_ends_at->equalTo(now()->addHours(168)));
        $this->assertTrue($access->access_ends_at->equalTo($access->trial_ends_at));
        $this->get($url)->assertRedirect('/dashboard');
        $this->travel(2)->days();
        // Simulate another verification event without allowing another trial.
        $user->forceFill(['email_verified_at' => null])->save();
        app(VerifyAccount::class)->handle($user);
        $this->assertTrue($access->trial_started_at->equalTo(AccountAccess::findOrFail($user->id)->trial_started_at));
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertDatabaseCount('account_access', 1);
        $this->assertTrue($access->access_ends_at->equalTo(AccountAccess::findOrFail($user->id)->access_ends_at));
    }

    public function test_wrong_actor_tampered_and_expired_verification_links_grant_nothing(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($user)->get($url.'x')->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
        $this->assertDatabaseCount('account_access', 0);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_login_rotates_session_logout_invalidates_and_failed_login_is_throttled(): void
    {
        $user = User::factory()->create();
        $this->get('/login');
        $before = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertNotSame($before, session()->getId());
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_recovery_response_is_generic_and_reset_token_is_one_use_and_expires(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($user);
        $access = AccountAccess::findOrFail($user->id)->access_ends_at;
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');
        $knownStatus = session('status');
        Notification::assertSentTo($user, AccountLink::class);
        $this->post('/forgot-password', ['email' => 'absent@example.test'])->assertSessionHas('status', $knownStatus);
        $token = Password::createToken($user);
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'];
        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->assertTrue($access->equalTo(AccountAccess::findOrFail($user->id)->access_ends_at));
        $this->travel(2)->minutes();
        $payload['token'] = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_mail_failure_can_be_retried_and_log_mailer_is_never_used(): void
    {
        config(['auth.mailer' => 'log']);
        $this->post('/register', $this->registration())->assertRedirect('/email/verify')->assertSessionHas('status', 'Email belum dapat dikirim. Silakan coba kirim ulang.');
        $this->assertDatabaseCount('account_access', 0);
        config(['auth.mailer' => 'array']);
        $this->post('/email/verification-notification')->assertRedirect('/email/verify');
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('account_access', 0);
    }

    public function test_sandbox_mail_flow_uses_real_rendered_messages_without_external_delivery(): void
    {
        config(['auth.mailer' => 'array']);
        $this->post('/register', $this->registration())->assertRedirect('/email/verify');
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->last()->getOriginalMessage();
        $html = $message->getHtmlBody();
        $this->assertStringContainsString('Verify Email Address', $html);
        preg_match('~href="([^"]*/email/verify/[^"]+)"~', $html, $match);
        $this->assertNotEmpty($match[1] ?? null);
        $this->get(html_entity_decode($match[1]))->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/forgot-password', ['email' => 'photo@example.test']);
        $this->assertCount(2, $transport->messages());
        $resetHtml = $transport->messages()->last()->getOriginalMessage()->getHtmlBody();
        preg_match('~href="([^"]*/reset-password/[^"]+)"~', $resetHtml, $resetMatch);
        $this->assertNotEmpty($resetMatch[1] ?? null);
        $resetUrl = html_entity_decode($resetMatch[1]);
        $token = basename(parse_url($resetUrl, PHP_URL_PATH));
        $this->get($resetUrl)->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/reset-password')->where('email', 'photo@example.test')->where('token', $token));
        $this->post('/reset-password', ['email' => 'photo@example.test', 'token' => $token, 'password' => 'MailResetPassword123', 'password_confirmation' => 'MailResetPassword123'])->assertRedirect('/login');
        $this->post('/login', ['email' => 'photo@example.test', 'password' => 'MailResetPassword123'])->assertRedirect('/dashboard');
        $this->assertDatabaseCount('account_access', 1);
    }

    public function test_an_old_authenticated_session_is_rejected_after_password_change(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->forceFill(['password' => 'NewSessionPassword123'])->save();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_malformed_login_and_auth_csrf_are_denied(): void
    {
        $this->post('/login', ['email' => ['malformed'], 'password' => 'synthetic'])->assertSessionHasErrors('email');
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => 'synthetic@example.test', 'password' => 'synthetic'])->assertStatus(419);
        $this->post('/register', $this->registration())->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_logged_out_verification_resumes_after_login_and_external_return_urls_are_ignored(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $this->get($url)->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($url);
        $this->get($url)->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->withSession(['url.intended' => 'https://external.example.test/email/verify/1/hash'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }
}
