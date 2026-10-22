<?php

namespace Tests\Feature;

use App\Actions\Billing\ManageVoucher;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherVersion;
use App\Services\Billing\VoucherQuote;
use App\Services\Billing\VoucherUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\VoucherFixture as F;
use Tests\TestCase;

class VoucherManagementTest extends TestCase
{
    use RefreshDatabase;

    private function edit(Voucher $voucher, array $overrides = []): array
    {
        $data = F::terms(['expected_version' => $voucher->currentVersion->number, ...$overrides]);
        unset($data['code']);

        return $data;
    }

    public function test_admin_creates_normalized_unique_code_and_audited_terms_without_an_entitlement(): void
    {
        $admin = F::admin();
        $this->actingAs($admin)->post('/admin/vouchers', F::terms(['code' => '  launch10  ']))->assertRedirect();
        $voucher = Voucher::sole();
        $this->assertSame('LAUNCH10', $voucher->code);
        $this->assertSame(1, $voucher->currentVersion->number);
        $this->assertSame(10, $voucher->currentVersion->discount_value);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'voucher_id' => $voucher->id,
            'voucher_version_id' => $voucher->current_version_id, 'action' => 'voucher.created', 'reason' => 'Synthetic voucher verification']);
        $this->postJson('/admin/vouchers', F::terms(['code' => 'Launch10']))->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseCount('voucher_versions', 1);
        $this->assertDatabaseCount('account_access', 0);
        $this->assertDatabaseCount('voucher_reservations', 0);
        $this->assertDatabaseCount('voucher_redemptions', 0);
    }

    public function test_invalid_terms_never_create_a_version_or_audit(): void
    {
        $this->actingAs(F::admin());
        $invalid = [
            ['discount_value', 0], ['discount_value', 100], ['discount_value', -1], ['discount_value', 1.5],
            ['discount_value', '9223372036854775808'], ['discount_type', 'cashback'],
            ['total_limit', 0], ['total_limit', -1], ['total_limit', 2.5], ['per_user_limit', 0], ['per_user_limit', -2],
            ['ends_at', now()->subDays(2)->format('Y-m-d\TH:i:sP')], ['ends_at', 'invalid'],
            ['starts_at', '2026-10-23T08:00:00'], ['enabled', 'yes'], ['code', '   '], ['code', "CODE\nINTERNAL"], ['reason', '  '],
        ];
        foreach ($invalid as [$field, $value]) {
            $this->postJson('/admin/vouchers', F::terms([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach ([0, -10, 1.2] as $value) {
            $this->postJson('/admin/vouchers', F::terms(['discount_type' => 'fixed', 'discount_value' => $value]))->assertUnprocessable()->assertJsonValidationErrors('discount_value');
        }
        $start = now()->format('Y-m-d\TH:i:sP');
        $this->postJson('/admin/vouchers', F::terms(['starts_at' => $start, 'ends_at' => $start]))->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('voucher_versions', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_wita_instants_survive_storage_and_unchanged_edits_without_timezone_drift(): void
    {
        $admin = F::admin();
        $terms = ['starts_at' => '2026-10-23T08:15:12+08:00', 'ends_at' => '2026-11-23T08:15:12+08:00'];
        $this->actingAs($admin)->post('/admin/vouchers', F::terms($terms))->assertRedirect();
        $voucher = Voucher::sole();
        $this->assertSame('2026-10-23T00:15:12+00:00', $voucher->currentVersion->starts_at->toIso8601String());
        $this->assertSame('2026-11-23T00:15:12+00:00', $voucher->currentVersion->ends_at->toIso8601String());
        $this->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, $terms))->assertRedirect();
        $this->assertTrue($voucher->currentVersion->starts_at->equalTo($voucher->fresh()->currentVersion->starts_at));
        $this->assertTrue($voucher->currentVersion->ends_at->equalTo($voucher->fresh()->currentVersion->ends_at));
    }

    public function test_validation_redirects_to_the_actual_form_without_relying_on_a_referrer_or_previous_session_page(): void
    {
        $admin = F::admin();
        $voucher = F::create($admin);
        $this->actingAs($admin)->withSession(['_previous' => ['url' => route('admin.vouchers.show', $voucher)]])
            ->post('/admin/vouchers', F::terms())->assertRedirect(route('admin.vouchers.create'))->assertSessionHasErrors('code');
        $this->withSession(['_previous' => ['url' => route('admin.vouchers.index')]])
            ->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['expected_version' => 0]))
            ->assertRedirect(route('admin.vouchers.show', $voucher))->assertSessionHasErrors('expected_version');
        $this->withSession(['_previous' => ['url' => route('admin.vouchers.index')]])
            ->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['expected_version' => 999]))
            ->assertRedirect(route('admin.vouchers.show', $voucher))->assertSessionHasErrors('expected_version');
    }

    public function test_versions_are_retained_on_edit_deactivation_and_reactivation_and_stale_forms_are_rejected(): void
    {
        $admin = F::admin();
        $voucher = F::create($admin);
        $originalId = $voucher->current_version_id;
        $quote = VoucherQuote::calculate($voucher->currentVersion, 29000, 1);
        $stale = $this->edit($voucher, ['discount_value' => 11]);
        $this->actingAs($admin)->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['discount_type' => 'fixed', 'discount_value' => 5000]))->assertRedirect();
        $this->assertSame(10, VoucherVersion::findOrFail($originalId)->discount_value);
        $this->putJson('/admin/vouchers/'.$voucher->id, $stale)->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $voucher = $voucher->fresh();
        $this->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['enabled' => false]))->assertRedirect();
        $voucher = $voucher->fresh();
        $this->assertFalse($voucher->currentVersion->enabled);
        $this->assertSame(26100, $quote->payable);
        // Existing pending-order versions remain identifiable and calculable;
        // checkout must separately use/recheck the active root for NEW orders.
        $this->assertSame(26100, VoucherQuote::calculate(VoucherVersion::findOrFail($originalId), 29000, 1)->payable);
        $this->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['enabled' => true]))->assertRedirect();
        $this->assertDatabaseCount('voucher_versions', 4);
        foreach (['voucher.created', 'voucher.updated', 'voucher.deactivated', 'voucher.reactivated'] as $action) {
            $this->assertDatabaseHas('audit_events', ['voucher_id' => $voucher->id, 'action' => $action]);
        }
        $this->putJson('/admin/vouchers/'.$voucher->id, [...$this->edit($voucher->fresh()), 'code' => 'NEWCODE'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->delete('/admin/vouchers/'.$voucher->id)->assertMethodNotAllowed();
    }

    public function test_limits_include_held_and_successful_usage_across_versions_and_all_users(): void
    {
        $admin = F::admin();
        $voucher = F::create($admin);
        $one = User::factory()->create();
        $two = User::factory()->create();
        F::reservation($voucher, $one, 'consumed');
        F::reservation($voucher, $one, 'held');
        F::reservation($voucher, $two, 'released');
        $this->actingAs($admin)->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['total_limit' => 4, 'per_user_limit' => 3]))->assertRedirect();
        $voucher = $voucher->fresh();
        F::reservation($voucher, $two);
        $this->putJson('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['total_limit' => 2]))->assertUnprocessable()->assertJsonValidationErrors('total_limit');
        $this->putJson('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['per_user_limit' => 1]))->assertUnprocessable()->assertJsonValidationErrors('per_user_limit');
        $this->assertDatabaseCount('voucher_versions', 2);
        $this->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher, ['total_limit' => 3, 'per_user_limit' => 2]))->assertRedirect();
        $this->assertSame(['held' => 2, 'used' => 1, 'released' => 1], app(VoucherUsage::class)->summary($voucher->id));
        $this->assertSame(['total' => 3, 'per_user' => 2], app(VoucherUsage::class)->capacity($voucher->id));
        $this->put('/admin/vouchers/'.$voucher->id, $this->edit($voucher->fresh()))->assertRedirect();
        $this->assertNull($voucher->fresh()->currentVersion->total_limit);
        $this->assertNull($voucher->fresh()->currentVersion->per_user_limit);
    }

    public function test_owner_reports_are_paginated_version_identified_and_scoped_to_the_requested_code(): void
    {
        $admin = F::admin();
        $voucher = F::create($admin);
        $other = F::create($admin, ['code' => 'OTHER']);
        $user = User::factory()->create();
        for ($i = 0; $i < 22; $i++) {
            F::reservation($voucher, $user, $i === 0 ? 'consumed' : 'held');
        }
        F::reservation($other, $user, 'consumed');
        $this->actingAs($admin)->get('/admin/vouchers/'.$voucher->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/voucher')->where('voucher.code', $voucher->code)
            ->where('usage.held', 21)->where('usage.used', 1)->has('reservations.data', 20)->where('reservations.total', 22)
            ->has('redemptions.data', 1)->where('redemptions.data.0.number', 1)->has('versions.data', 1)->has('audit.data', 1));
        $this->get('/admin/vouchers/'.$voucher->id.'?reservations_page=2')->assertInertia(fn (Assert $page) => $page->has('reservations.data', 2));
        $this->get('/admin/vouchers')->assertInertia(fn (Assert $page) => $page->component('admin/vouchers')->has('vouchers.data', 2));
    }

    public function test_every_admin_route_denies_photographers_unverified_and_suspended_admins_and_anonymous_visitors(): void
    {
        $admin = F::admin();
        $voucher = F::create($admin);
        $paths = ['/admin/vouchers', '/admin/vouchers/create', '/admin/vouchers/'.$voucher->id];
        foreach ([User::factory()->create(), F::admin(), F::admin()] as $i => $user) {
            if ($i === 1) {
                $user->forceFill(['email_verified_at' => null])->save();
            } elseif ($i === 2) {
                $user->forceFill(['suspended_at' => now()])->save();
            }
            $this->actingAs($user);
            foreach ($paths as $path) {
                $this->getJson($path)->assertForbidden();
            }
            $this->postJson('/admin/vouchers', F::terms())->assertForbidden();
            $this->putJson('/admin/vouchers/'.$voucher->id, $this->edit($voucher))->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        foreach ($paths as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->postJson('/admin/vouchers', F::terms())->assertUnauthorized();
        $this->putJson('/admin/vouchers/'.$voucher->id, $this->edit($voucher))->assertUnauthorized();
        $this->assertDatabaseCount('voucher_versions', 1);
    }

    public function test_action_rechecks_persisted_admin_state_instead_of_trusting_a_stale_actor(): void
    {
        $admin = F::admin();
        User::whereKey($admin->id)->update(['role' => 'photographer']);
        $this->expectException(HttpException::class);
        app(ManageVoucher::class)->save($admin, F::terms());
    }

    private function rejectsSql(callable $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('Database must reject historical mutation or invalid identity.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_database_protects_normalization_version_identity_history_and_one_redemption_per_reservation(): void
    {
        $admin = F::admin();
        $voucher = F::create($admin);
        $other = F::create($admin, ['code' => 'OTHER']);
        $user = User::factory()->create();
        $reservation = F::reservation($voucher, $user, 'consumed');
        $this->rejectsSql(fn () => DB::table('vouchers')->insert(['code' => ' lower ']));
        $this->rejectsSql(fn () => DB::table('vouchers')->insert(['code' => $voucher->code]));
        $this->rejectsSql(fn () => DB::table('vouchers')->where('id', $voucher->id)->update(['code' => 'RENAMED']));
        $this->rejectsSql(fn () => DB::table('vouchers')->where('id', $voucher->id)->delete());
        $this->rejectsSql(fn () => DB::table('voucher_versions')->where('id', $voucher->current_version_id)->update(['discount_value' => 99]));
        $this->rejectsSql(fn () => DB::table('voucher_versions')->where('id', $voucher->current_version_id)->delete());
        $this->rejectsSql(fn () => DB::table('vouchers')->where('id', $voucher->id)->update(['current_version_id' => $other->current_version_id]));
        $this->rejectsSql(fn () => DB::table('voucher_reservations')->insert(['order_id' => (string) Str::uuid(), 'voucher_id' => $other->id, 'voucher_version_id' => $voucher->current_version_id, 'user_id' => $user->id]));
        $this->rejectsSql(fn () => DB::table('voucher_redemptions')->insert(['reservation_id' => $reservation]));
        $this->rejectsSql(fn () => DB::table('voucher_redemptions')->where('reservation_id', $reservation)->delete());
        $this->assertDatabaseCount('voucher_versions', 2);
        $this->assertDatabaseCount('voucher_redemptions', 1);
    }

    public function test_integer_quotes_match_policy_round_down_fixed_and_representable_extremes(): void
    {
        $voucher = F::create(F::admin());
        $quote = VoucherQuote::calculate($voucher->currentVersion, 29000, 1);
        $this->assertSame([29000, 2900, 26100, 'IDR', $voucher->id, $voucher->current_version_id], [$quote->base, $quote->discount, $quote->payable, $quote->currency, $quote->voucherId, $quote->versionId]);
        $this->assertSame(2900, VoucherQuote::calculate($voucher->currentVersion, 29009, 1)->discount);
        $fixed = F::create(F::admin(), ['code' => 'FIXED', 'discount_type' => 'fixed', 'discount_value' => 4999]);
        $this->assertSame(24001, VoucherQuote::calculate($fixed->currentVersion, 29000, 24001)->payable);
        $large = F::create(F::admin(), ['code' => 'LARGE', 'discount_value' => 99]);
        $this->assertSame(9131138316486228048, VoucherQuote::calculate($large->currentVersion, PHP_INT_MAX, 1)->discount);
    }

    public function test_quote_start_is_inclusive_end_exclusive_and_nonpositive_or_below_channel_minimum_is_rejected(): void
    {
        $voucher = F::create(F::admin());
        $version = $voucher->currentVersion;
        $this->assertSame(26100, VoucherQuote::calculate($version, 29000, 1, $version->starts_at)->payable);
        $this->assertSame(26100, VoucherQuote::calculate($version, 29000, 1, $version->ends_at->subMicrosecond())->payable);
        $fixed = F::create(F::admin(), ['code' => 'FIXED', 'discount_type' => 'fixed', 'discount_value' => 29000]);
        $disabled = F::create(F::admin(), ['code' => 'DISABLED', 'enabled' => false]);
        $cases = [[$version, 29000, 26101, null], [$version, 0, 1, null], [$version, 29000, 0, null],
            [$fixed->currentVersion, 29000, 1, null], [$fixed->currentVersion, 10000, 1, null],
            [$version, 29000, 1, $version->starts_at->subMicrosecond()], [$version, 29000, 1, $version->ends_at],
            [$disabled->currentVersion, 29000, 1, CarbonImmutable::now()]];
        foreach ($cases as $args) {
            try {
                VoucherQuote::calculate(...$args);
                $this->fail('Invalid quote must not be clipped or accepted.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('voucher', $e->errors());
            }
        }
    }
}
