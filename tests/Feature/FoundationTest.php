<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_auth_boundaries_render_inertia_and_security_headers(): void
    {
        $response = $this->get('/');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/welcome'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $response->headers->get('Content-Security-Policy'));
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/login'));
        $this->post('/login', ['email' => 'synthetic@example.test', 'password' => 'synthetic'])->assertStatus(405);
    }

    public function test_protected_shells_require_a_verified_session(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/email/verify');
        $this->actingAs($user)->get('/admin')->assertRedirect('/email/verify');
    }

    public function test_photographer_cannot_access_admin_or_mass_assign_role(): void
    {
        $user = User::factory()->create();
        $user->fill(['role' => 'admin'])->save();
        $this->assertSame('photographer', $user->fresh()->role);
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('photographer/dashboard')->where('auth.user', ['name' => $user->name]));
        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_gate_uses_server_persisted_role(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'admin';
        $admin->save();
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard'));
    }

    public function test_client_boundary_is_closed_until_capability_authorization_exists(): void
    {
        $this->get(route('client.gallery', ['capability' => 'invalid']))->assertNotFound();
    }

    public function test_previews_are_local_only_even_with_routes_registered(): void
    {
        foreach (['public', 'auth', 'photographer', 'admin', 'client'] as $surface) {
            $this->get('/_foundation/'.$surface)->assertOk();
        }
        $this->app->instance('env', 'production');
        $this->get('/_foundation/admin')->assertNotFound();
    }

    public function test_real_postgresql_schema_and_password_security(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertNotEmpty(DB::selectOne('select version()')->version);
        $this->assertSame('UTC', DB::selectOne('show timezone')->TimeZone);
        $user = User::factory()->create(['password' => 'synthetic-password']);
        $this->assertTrue(Hash::check('synthetic-password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertTrue(config('session.http_only'));
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertSame('session', config('auth.guards.web.driver'));
    }

    public function test_web_mutations_keep_framework_csrf_protection(): void
    {
        Route::middleware('web')->post('/_csrf-probe', fn () => response()->noContent());
        $this->app->instance('env', 'local');
        $this->post('/_csrf-probe')->assertStatus(419);
    }
}
