<?php

namespace Tests\Feature;

use App\Actions\Auth\SetAccountSuspension;
use App\Actions\Auth\VerifyAccount;
use App\Models\AccountAccess;
use App\Models\DriveProofRun;
use App\Models\User;
use App\Policies\TenantResourcePolicy;
use App\Services\Access\AccountAction as A;
use App\Services\Access\AccountPolicy;
use App\Services\Access\GalleryGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccessPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function trial(): User
    {
        $user = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($user);

        return $user->fresh();
    }

    public function test_exact_entitlement_boundary_and_distinct_suspension_matrix_use_current_database_state(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->trial();
        $policy = app(AccountPolicy::class);
        foreach ([A::Read, A::Billing, A::Mutate, A::Checkout, A::ClientRead, A::ClientWrite, A::Download] as $action) {
            $this->assertTrue($policy->allows($user, $action));
        }
        $end = AccountAccess::findOrFail($user->id)->access_ends_at;
        $this->travelTo($end->subSecond());
        $this->assertTrue($policy->allows($user, A::Mutate));
        $this->travelTo($end);
        $this->assertSame('expired', $policy->state($user));
        foreach ([A::Read, A::Billing, A::Checkout, A::ClientRead, A::Download] as $action) {
            $this->assertTrue($policy->allows($user, $action));
        }
        $this->assertFalse($policy->allows($user, A::Mutate));
        $this->assertFalse($policy->allows($user, A::ClientWrite));
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('accountState', 'expired'));
        DB::table('users')->where('id', $user->id)->update(['suspended_at' => now()]);
        $this->assertSame('suspended', $policy->state($user));
        $this->assertTrue($policy->allows($user, A::Billing));
        foreach ([A::Read, A::Checkout, A::Mutate, A::ClientRead, A::ClientWrite, A::Download] as $action) {
            $this->assertFalse($policy->allows($user, $action));
        }
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('accountState', 'suspended'));
        $this->assertDatabaseCount('account_access', 1);
    }

    public function test_pro_projection_is_active_without_a_content_quota_and_unverified_and_admin_have_no_tenant_bypass(): void
    {
        $policy = app(AccountPolicy::class);
        $unverified = User::factory()->unverified()->create();
        foreach (A::cases() as $action) {
            $this->assertFalse($policy->allows($unverified, $action));
        }
        $user = $this->trial();
        AccountAccess::findOrFail($user->id)->forceFill(['has_paid_access' => true, 'access_ends_at' => now()->addDays(30)])->save();
        $this->assertSame('pro', $policy->state($user));
        $this->assertTrue($policy->allows($user, A::Mutate));
        $user->forceFill(['role' => 'admin'])->save();
        $this->assertTrue($policy->allows($user, A::Admin));
        $this->assertFalse($policy->allows($user, A::Mutate));
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->get('/admin')->assertOk();
        DB::table('users')->where('id', $user->id)->update(['role' => 'photographer']);
        $this->get('/admin')->assertForbidden();
    }

    public function test_cross_tenant_substitution_is_denied_in_shared_read_write_job_media_and_billing_boundary(): void
    {
        $owner = $this->trial();
        $other = $this->trial();
        $run = DriveProofRun::create(['user_id' => $owner->id, 'state' => 'imported', 'root' => ['id' => 'synthetic']]);
        $actions = ['read' => A::Read, 'write' => A::Mutate, 'job' => A::Mutate, 'media' => A::Download, 'billing' => A::Billing];
        // Test-only routes exercise the shared policy against a real persisted tenant
        // record. Future product routes must install this boundary for their own models.
        foreach ($actions as $path => $action) {
            Route::middleware(['web', 'auth', 'verified'])->get('/_access/'.$path.'/{run}', function (DriveProofRun $run) use ($action) {
                Gate::authorize('access', [$run, $action]);

                return response()->noContent();
            });
            $this->actingAs($other)->get('/_access/'.$path.'/'.$run->id.'?user_id='.$other->id)->assertNotFound();
            $this->actingAs($owner)->get('/_access/'.$path.'/'.$run->id)->assertNoContent();
        }
        $run->user_id = $other->id; // Unsaved ownership forgery must also fail.
        $this->assertFalse(app(TenantResourcePolicy::class)->access($other, $run, A::Read)->allowed());
        $other->forceFill(['role' => 'admin'])->save();
        $this->assertFalse(app(TenantResourcePolicy::class)->access($other, $run, A::Download)->allowed());
        // A queued action rechecking the persisted owner loses write rights at expiry.
        AccountAccess::findOrFail($owner->id)->forceFill(['access_ends_at' => now()])->save();
        $this->assertFalse(app(TenantResourcePolicy::class)->access($owner, $run, A::Mutate)->allowed());
        $this->assertTrue(app(TenantResourcePolicy::class)->access($owner, $run, A::Download)->allowed());
    }

    public function test_gallery_gates_remain_independent_of_expiry_and_reject_new_media_requests(): void
    {
        $owner = $this->trial();
        $gate = app(GalleryGate::class);
        $allow = fn (A $action, array $overrides = []) => $gate->allows(...array_replace([
            'owner' => $owner, 'action' => $action, 'capabilityValid' => true, 'published' => true,
            'archived' => false, 'deadline' => null, 'passwordRequired' => true,
            'passwordSessionValid' => true, 'gateVersion' => 2, 'sessionGateVersion' => 2, 'selectionLocked' => false,
        ], $overrides));
        $this->assertTrue($allow(A::ClientWrite));
        $this->assertFalse($allow(A::ClientWrite, ['selectionLocked' => true]));
        $this->assertTrue($allow(A::ClientRead, ['selectionLocked' => true]));
        AccountAccess::findOrFail($owner->id)->forceFill(['access_ends_at' => now()])->save();
        $this->assertFalse($allow(A::ClientWrite));
        $this->assertTrue($allow(A::Download));
        foreach ([['capabilityValid' => false], ['published' => false], ['archived' => true], ['deadline' => now()], ['passwordSessionValid' => false], ['sessionGateVersion' => 1]] as $overrides) {
            $this->assertFalse($allow(A::Download, $overrides));
        }
        $this->assertTrue($allow(A::Download, ['passwordRequired' => false, 'passwordSessionValid' => false]));
        DB::table('users')->where('id', $owner->id)->update(['suspended_at' => now()]);
        $this->assertFalse($allow(A::Download));
        $this->assertFalse($allow(A::ClientRead));
    }

    public function test_admin_provisioning_requires_existing_verified_owner_and_is_idempotently_audited(): void
    {
        $user = User::factory()->unverified()->create();
        $this->artisan('snappik:provision-admin', ['email' => $user->email])->assertFailed();
        $this->artisan('snappik:provision-admin', ['email' => 'absent@example.test'])->assertFailed();
        app(VerifyAccount::class)->handle($user);
        $this->artisan('snappik:provision-admin', ['email' => strtoupper($user->email)])->assertSuccessful();
        $this->artisan('snappik:provision-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertSame('admin', $user->fresh()->role);
        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.provisioned', 'target_user_id' => $user->id, 'actor_id' => null]);
    }

    public function test_suspension_and_reactivation_are_admin_only_audited_and_preserve_access_period(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $user = $this->trial();
        $end = AccountAccess::findOrFail($user->id)->access_ends_at;
        $action = app(SetAccountSuspension::class);
        $action->handle($admin, $user, true, 'Synthetic suspension test');
        $action->handle($admin, $user, true, 'Duplicate request');
        $this->assertNotNull($user->fresh()->suspended_at);
        $this->actingAs($user)->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $action->handle($admin, $user, false, 'Synthetic reactivation test');
        $this->assertNull($user->fresh()->suspended_at);
        $this->assertSame('trial', app(AccountPolicy::class)->state($user));
        $this->assertTrue($end->equalTo(AccountAccess::findOrFail($user->id)->access_ends_at));
        $this->assertDatabaseCount('audit_events', 2);
        $this->expectException(HttpException::class);
        $action->handle($user, $admin, true, 'Forbidden');
    }
}
