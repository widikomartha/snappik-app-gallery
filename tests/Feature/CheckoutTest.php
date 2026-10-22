<?php

namespace Tests\Feature;

use App\Actions\Billing\Checkout;
use App\Actions\Billing\ManageVoucher;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        F::configure();
    }

    public function test_server_quote_order_and_reservation_match_and_replay_retains_original_terms(): void
    {
        $buyer = F::buyer();
        $admin = V::admin();
        $voucher = V::create($admin, ['total_limit' => 1, 'per_user_limit' => 1]);
        $gateway = F::gateway();
        $this->actingAs($buyer)->postJson('/billing/quote', ['voucher' => ' synthetic10 '])->assertOk()
            ->assertJsonPath('quote.base_idr', 29000)->assertJsonPath('quote.discount_idr', 2900)->assertJsonPath('quote.payable_idr', 26100);
        $quote = app(Checkout::class)->quote($buyer, $voucher->code);
        $data = ['idempotency_key' => (string) Str::uuid(), 'quote_token' => $quote['quote_token']];
        $id = $this->postJson('/billing/orders', $data)->assertOk()->json('id');
        $order = Order::findOrFail($id);
        $this->assertSame([29000, 2900, 26100, 30], [$order->base_idr, $order->discount_idr, $order->payable_idr, $order->duration_days]);
        $this->assertDatabaseHas('voucher_reservations', ['order_id' => $id, 'user_id' => $buyer->id, 'state' => 'held', 'voucher_version_id' => $voucher->current_version_id]);
        app(ManageVoucher::class)->save($admin, [...V::terms(), 'expected_version' => 1, 'enabled' => false], $voucher->id);
        $this->travel(16)->minutes();
        $this->postJson('/billing/orders', $data)->assertOk()->assertJsonPath('id', $id);
        $this->assertSame(1, $gateway->creates);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('voucher_reservations', 1);
        $this->get('/billing/orders/'.$id)->assertInertia(fn (Assert $page) => $page->component('photographer/order')->where('order.payable_idr', 26100)->where('order.canOpen', true)->missing('order.snap_token')->missing('order.checkout_url'));
        $this->get('/billing/orders/'.$id.'/open')->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/synthetic-snap-token');
        $this->assertStringNotContainsString('synthetic-snap-token', DB::table('orders')->where('id', $id)->value('snap_token'));
        $this->assertFalse($buyer->accountAccess->has_paid_access);
    }

    public function test_quote_tampering_owner_substitution_and_purchase_field_tampering_fail(): void
    {
        $buyer = F::buyer();
        $other = F::buyer();
        $quote = app(Checkout::class)->quote($buyer, null);
        $data = ['idempotency_key' => (string) Str::uuid(), 'quote_token' => $quote['quote_token']];
        $this->actingAs($other)->postJson('/billing/orders', $data)->assertForbidden();
        $this->actingAs($buyer)->postJson('/billing/orders', [...$data, 'quote_token' => $data['quote_token'].'bad'])->assertUnprocessable();
        foreach (['user_id', 'price', 'duration_days', 'payable_idr', 'voucher', 'currency'] as $field) {
            $this->postJson('/billing/orders', [...$data, $field => '1'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stale_plan_voucher_and_expired_quote_require_new_review(): void
    {
        $buyer = F::buyer();
        $admin = V::admin();
        $voucher = V::create($admin);
        $checkout = app(Checkout::class);
        $quote = $checkout->quote($buyer, $voucher->code);
        app(ManageVoucher::class)->save($admin, [...V::terms(), 'expected_version' => 1, 'discount_value' => 20], $voucher->id);
        $this->actingAs($buyer)->postJson('/billing/orders', ['quote_token' => $quote['quote_token'], 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('quote_token');
        $quote = $checkout->quote($buyer, null);
        $new = DB::table('plan_versions')->insertGetId(['price_idr' => 35000, 'duration_days' => 30]);
        DB::table('plans')->where('code', 'pro')->update(['current_version_id' => $new]);
        $this->postJson('/billing/orders', ['quote_token' => $quote['quote_token'], 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $quote = $checkout->quote($buyer, null);
        $this->travel(15)->minutes();
        $this->postJson('/billing/orders', ['quote_token' => $quote['quote_token'], 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pending_orders_keep_original_plan_price_after_future_price_change(): void
    {
        $buyer = F::buyer();
        $old = F::reserve($buyer);
        $new = DB::table('plan_versions')->insertGetId(['price_idr' => 39000, 'duration_days' => 30]);
        DB::table('plans')->where('code', 'pro')->update(['current_version_id' => $new]);
        $next = F::reserve($buyer);
        $this->assertSame(29000, $old->fresh()->payable_idr);
        $this->assertSame(39000, $next->payable_idr);
    }

    public function test_changed_input_replay_does_not_create_another_order(): void
    {
        $buyer = F::buyer();
        $voucher = V::create(V::admin());
        $checkout = app(Checkout::class);
        $key = (string) Str::uuid();
        $checkout->reserve($buyer, $key, $checkout->quote($buyer, null)['quote_token']);
        $this->actingAs($buyer)->postJson('/billing/orders', ['idempotency_key' => $key, 'quote_token' => $checkout->quote($buyer, $voucher->code)['quote_token']])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('voucher_reservations', 0);
    }

    public function test_per_user_limit_spans_pending_orders_and_versions(): void
    {
        $buyer = F::buyer();
        $admin = V::admin();
        $voucher = V::create($admin, ['per_user_limit' => 1]);
        F::reserve($buyer, $voucher->code);
        app(ManageVoucher::class)->save($admin, [...V::terms(), 'expected_version' => 1, 'per_user_limit' => 1, 'discount_value' => 20], $voucher->id);
        $this->actingAs($buyer)->postJson('/billing/quote', ['voucher' => $voucher->code])->assertUnprocessable()->assertJsonValidationErrors('voucher');
        F::reserve(F::buyer(), $voucher->code);
        $this->assertDatabaseCount('voucher_reservations', 2);
    }

    public function test_nonpositive_below_minimum_and_ineligible_vouchers_do_not_hold_capacity(): void
    {
        $buyer = F::buyer();
        $admin = V::admin();
        V::create($admin, ['code' => 'FREE', 'discount_type' => 'fixed', 'discount_value' => 29000]);
        V::create($admin, ['code' => 'NEGATIVE', 'discount_type' => 'fixed', 'discount_value' => 30000]);
        V::create($admin, ['code' => 'MINIMUM', 'discount_type' => 'fixed', 'discount_value' => 28999]);
        V::create($admin, ['code' => 'DISABLED', 'enabled' => false]);
        V::create($admin, ['code' => 'FUTURE', 'starts_at' => now()->addDay()->toIso8601String()]);
        V::create($admin, ['code' => 'EXPIRED', 'starts_at' => now()->subDays(3)->toIso8601String(), 'ends_at' => now()->subDay()->toIso8601String()]);
        config(['billing.qris_minimum' => 2]);
        foreach (['FREE', 'NEGATIVE', 'MINIMUM', 'DISABLED', 'FUTURE', 'EXPIRED', 'UNKNOWN'] as $code) {
            $this->actingAs($buyer)->postJson('/billing/quote', ['voucher' => $code])->assertUnprocessable();
        }
        config(['billing.qris_minimum' => 1]);
        $this->postJson('/billing/quote', ['voucher' => 'MINIMUM'])->assertOk()->assertJsonPath('quote.payable_idr', 1);
        $this->assertDatabaseCount('voucher_reservations', 0);
    }

    public function test_routes_scope_orders_and_deny_checkout_for_stale_suspended_admin_or_unverified_actors(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        F::gateway();
        $other = F::buyer();
        $this->actingAs($other)->get('/billing/orders/'.$order->id)->assertNotFound();
        $this->post('/billing/orders/'.$order->id.'/resume')->assertNotFound();
        $this->get('/billing/orders/'.$order->id.'/open')->assertNotFound();
        $this->get('/billing')->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
        User::whereKey($buyer->id)->update(['suspended_at' => now()]);
        $this->actingAs($buyer)->get('/billing')->assertOk();
        $this->get('/billing/orders/'.$order->id)->assertOk();
        $this->postJson('/billing/quote')->assertForbidden();
        $this->post('/billing/orders/'.$order->id.'/resume')->assertForbidden();
        $this->get('/billing/orders/'.$order->id.'/open')->assertForbidden();
        foreach ([V::admin(), User::factory()->unverified()->create()] as $denied) {
            $this->actingAs($denied)->getJson('/billing')->assertForbidden();
            $this->postJson('/billing/quote')->assertForbidden();
            $this->postJson('/billing/orders')->assertStatus($denied->hasVerifiedEmail() ? 422 : 403);
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/billing')->assertUnauthorized();
        $this->postJson('/billing/orders')->assertUnauthorized();
    }

    public function test_expired_account_can_buy_and_missing_configuration_creates_no_order(): void
    {
        $buyer = F::buyer();
        $this->travel(8)->days();
        $this->actingAs($buyer)->postJson('/billing/quote')->assertOk();
        config(['billing.server_key' => '']);
        $this->postJson('/billing/quote')->assertUnprocessable();
        $this->get('/billing')->assertInertia(fn (Assert $page) => $page->where('configured', false)->where('canCheckout', false));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_database_protects_purchase_snapshots_and_reservation_identity(): void
    {
        $buyer = F::buyer();
        $voucher = V::create(V::admin());
        $order = F::reserve($buyer, $voucher->code);
        foreach ([fn () => DB::table('orders')->where('id', $order->id)->update(['payable_idr' => 1]),
            fn () => DB::table('orders')->where('id', $order->id)->update(['user_id' => F::buyer()->id]),
            fn () => DB::table('plan_versions')->where('id', $order->plan_version_id)->update(['price_idr' => 1]),
            fn () => DB::table('voucher_reservations')->where('order_id', $order->id)->update(['user_id' => F::buyer()->id]),
            fn () => DB::table('orders')->where('id', $order->id)->delete()] as $write) {
            try {
                DB::transaction($write);
                $this->fail('Database must protect purchase history.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }
}
