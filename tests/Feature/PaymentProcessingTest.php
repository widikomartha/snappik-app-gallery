<?php

namespace Tests\Feature;

use App\Actions\Billing\ManageVoucher;
use App\Actions\Billing\ResumeCheckout;
use App\Jobs\ProcessPaymentEvent;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use App\Services\Billing\PaymentProcessing;
use App\Services\Billing\PaymentRecovery;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\PaymentFixture as P;
use Tests\Support\SelectionFixture;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class PaymentProcessingTest extends TestCase
{
    use RefreshDatabase, SelectionFixture;

    protected function setUp(): void
    {
        parent::setUp();
        F::configure();
        $this->travelTo(now()->startOfSecond());
    }

    private function check(Order $order, array $overrides = []): PaymentEvent
    {
        P::gateway([P::response($order, $overrides)]);
        $event = app(PaymentProcessing::class)->record($order, 'operator', queue: false);
        app(PaymentProcessing::class)->process($event->id);

        return $event->fresh();
    }

    public function test_callback_commits_one_safe_receipt_and_database_outbox_before_acknowledging(): void
    {
        $order = F::reserve(F::buyer());
        $payload = [...P::signed($order), 'customer_details' => ['email' => 'private@example.test'], 'irrelevant' => 'do-not-store'];
        $this->postJson('/webhooks/midtrans', $payload)->assertOk()->assertExactJson(['received' => true]);
        $this->postJson('/webhooks/midtrans', $payload)->assertOk();
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseHas('jobs', ['queue' => 'payments']);
        $this->assertDatabaseCount('jobs', 1);
        $stored = json_encode(DB::table('payment_events')->first()).DB::table('jobs')->value('payload');
        foreach (['signature_key', $payload['signature_key'], 'private@example.test', 'do-not-store', 'synthetic-test-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        $this->assertDatabaseCount('entitlement_grants', 0);
    }

    public function test_invalid_signature_identity_currency_channel_and_amount_are_rejected(): void
    {
        $order = F::reserve(F::buyer());
        $this->postJson('/webhooks/midtrans', [...P::signed($order), 'signature_key' => str_repeat('0', 128)])->assertForbidden();
        foreach ([['gross_amount' => '1.00'], ['merchant_id' => 'other'], ['currency' => 'USD'], ['payment_type' => 'credit_card'],
            ['order_id' => 'sp-00000000-0000-0000-0000-000000000000']] as $bad) {
            $this->postJson('/webhooks/midtrans', P::signed($order, $bad))->assertForbidden();
        }
        $this->postJson('/webhooks/midtrans', ['order_id' => []])->assertBadRequest();
        $this->postJson('/webhooks/midtrans', [...P::signed($order), 'extra' => str_repeat('a', 32768)])->assertBadRequest();
        $this->assertDatabaseCount('payment_events', 0);
        $this->assertDatabaseCount('entitlement_grants', 0);
    }

    public function test_intake_persistence_failure_is_retryable_and_does_not_acknowledge(): void
    {
        $order = F::reserve(F::buyer());
        $this->mock(PaymentProcessing::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('synthetic-private-diagnostic'));
        $this->postJson('/webhooks/midtrans', P::signed($order))->assertStatus(503)->assertDontSee('synthetic-private-diagnostic');
        $this->assertDatabaseCount('payment_events', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_outbox_insert_failure_rolls_back_receipt_and_provider_retry_can_recover(): void
    {
        $order = F::reserve(F::buyer());
        DB::unprepared("CREATE FUNCTION synthetic_outbox_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'synthetic queue interruption'; END; $$;
            CREATE TRIGGER synthetic_outbox_failure BEFORE INSERT ON jobs FOR EACH ROW EXECUTE FUNCTION synthetic_outbox_failure()");
        try {
            $this->postJson('/webhooks/midtrans', P::signed($order))->assertStatus(503);
            $this->assertDatabaseCount('payment_events', 0);
            $this->assertDatabaseCount('jobs', 0);
        } finally {
            DB::unprepared('DROP TRIGGER synthetic_outbox_failure ON jobs; DROP FUNCTION synthetic_outbox_failure()');
        }
        $this->postJson('/webhooks/midtrans', P::signed($order))->assertOk();
        P::gateway([P::response($order)]);
        $job = app('queue')->connection('payments')->pop('payments');
        $this->assertNotNull($job);
        $job->fire();
        $this->assertTrue($job->isDeleted());
        $this->assertSame('paid', $order->fresh()->state);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_receipt_survives_interruption_and_duplicate_execution_grants_and_redeems_once(): void
    {
        $buyer = F::buyer();
        $trialEnd = $buyer->accountAccess->access_ends_at;
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $this->postJson('/webhooks/midtrans', P::signed($order))->assertOk();
        $event = PaymentEvent::firstOrFail();
        P::gateway([P::response($order)]);
        (new ProcessPaymentEvent($event->id))->handle(app(PaymentProcessing::class));
        (new ProcessPaymentEvent($event->id))->handle(app(PaymentProcessing::class));
        $this->postJson('/webhooks/midtrans', P::signed($order))->assertOk();
        $this->assertSame('paid', $order->fresh()->state);
        $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($trialEnd->addHours(720)));
        $this->assertDatabaseCount('entitlement_grants', 1);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'consumed']);
        $this->assertDatabaseHas('payment_events', ['id' => $event->id, 'state' => 'processed', 'provider_state' => 'settlement']);
        $this->assertNotNull($event->fresh()->result_digest);
    }

    public function test_signed_settlement_and_browser_return_cannot_override_authoritative_pending(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $this->actingAs($buyer)->get('/billing/orders/'.$order->id.'?transaction_status=settlement&status_code=200')->assertOk();
        $this->postJson('/webhooks/midtrans', P::signed($order))->assertOk();
        P::gateway([P::response($order, ['transaction_status' => 'pending', 'status_code' => '201'])]);
        app(PaymentProcessing::class)->process(PaymentEvent::firstOrFail()->id);
        $this->assertSame('pending', $order->fresh()->state);
        $this->assertDatabaseCount('entitlement_grants', 0);
        $this->assertFalse($buyer->fresh()->accountAccess->has_paid_access);
    }

    public function test_authoritative_identity_money_time_fraud_and_channel_mismatches_grant_nothing_and_retain_hold(): void
    {
        $buyer = F::buyer();
        $voucher = V::create(V::admin());
        foreach ([['gross_amount' => '1.00'], ['gross_amount' => '26100.01'], ['currency' => 'USD'], ['order_id' => 'other'],
            ['merchant_id' => 'other'], ['payment_type' => 'gopay'], ['transaction_id' => null], ['fraud_status' => 'deny'],
            ['settlement_time' => null], ['settlement_time' => '2026-02-30 00:00:00'],
            ['settlement_time' => now('Asia/Jakarta')->addHour()->format('Y-m-d H:i:s')],
            ['settlement_time' => now('Asia/Jakarta')->subDay()->format('Y-m-d H:i:s')], ['status_code' => '202']] as $bad) {
            $order = F::reserve($buyer, $voucher->code);
            $event = $this->check($order, $bad);
            $this->assertSame('review', $event->state);
            $this->assertNotNull($order->fresh()->review_reason);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
        }
        $this->assertDatabaseCount('entitlement_grants', 0);
        $this->assertDatabaseCount('voucher_redemptions', 0);
    }

    public function test_provider_confirmed_nonpayment_releases_hold_while_unknown_keeps_it(): void
    {
        $buyer = F::buyer();
        $voucher = V::create(V::admin());
        foreach (['deny', 'cancel', 'expire', 'failure'] as $state) {
            $order = F::reserve($buyer, $voucher->code);
            $this->check($order, ['transaction_status' => $state, 'status_code' => '202']);
            $this->assertSame('failed', $order->fresh()->state);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'released']);
        }
        $order = F::reserve($buyer, $voucher->code);
        $this->travel(25)->hours();
        foreach ([new Response(404, [], '{"status_code":"404"}'), new Response(503, [], '{}')] as $response) {
            P::gateway([$response]);
            $event = app(PaymentProcessing::class)->record($order, 'operator', queue: false);
            app(PaymentProcessing::class)->process($event->id);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
        }
        $this->assertDatabaseCount('entitlement_grants', 0);
    }

    public function test_late_settlement_honors_original_disabled_voucher_after_released_capacity_was_resold(): void
    {
        $admin = V::admin();
        $voucher = V::create($admin, ['total_limit' => 1, 'per_user_limit' => 1]);
        $buyer = F::buyer();
        $order = F::reserve($buyer, $voucher->code);
        $this->check($order, ['transaction_status' => 'expire', 'status_code' => '202']);
        $other = F::reserve(F::buyer(), $voucher->code);
        app(ManageVoucher::class)->save($admin, V::terms(['enabled' => false, 'total_limit' => 1, 'per_user_limit' => 1, 'expected_version' => 1]), $voucher->id);
        $this->travel(40)->days();
        $event = $this->check($order);
        $this->assertSame('review', $event->state);
        $this->assertSame('paid', $order->fresh()->state);
        $this->assertSame(26100, $order->payable_idr);
        $this->assertSame('late_settlement_after_release', $order->fresh()->review_reason);
        $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq(now()->addHours(720)));
        $this->assertDatabaseHas('voucher_reservations', ['order_id' => $other->id, 'state' => 'held']);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $this->check($order);
        $this->assertDatabaseCount('entitlement_grants', 1);
    }

    public function test_pending_missing_or_refund_cannot_reopen_a_provider_confirmed_terminal_order(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $order->update(['snap_token' => 'synthetic-page', 'checkout_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/synthetic-page']);
        $this->check($order, ['transaction_status' => 'expire', 'status_code' => '202']);
        foreach (['pending', 'pending', 'refund', 'pending'] as $state) {
            $this->check($order, ['transaction_status' => $state]);
            $this->assertSame('failed', $order->fresh()->state);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'released']);
        }
        $this->assertNotNull($order->fresh()->review_reason);
        P::gateway([]);
        $this->assertSame('failed', app(ResumeCheckout::class)->handle($buyer, $order->id)->state);
        $this->actingAs($buyer)->get('/billing/orders/'.$order->id.'/open')->assertConflict();
        $this->assertDatabaseCount('entitlement_grants', 0);
    }

    public function test_stale_pending_failure_refund_and_unknown_never_regress_paid_or_extend_again(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $this->check($order);
        $end = $buyer->fresh()->accountAccess->access_ends_at;
        foreach (['pending', 'expire', 'deny', 'settlement', 'refund', 'partial_refund', 'chargeback', 'surprise'] as $state) {
            $this->check($order, ['transaction_status' => $state]);
            $this->assertSame('paid', $order->fresh()->state);
            $this->assertSame('settlement', $order->fresh()->provider_state);
            $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($end));
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'consumed']);
        }
        $this->assertNotNull($order->fresh()->review_reason);
        $this->assertDatabaseCount('entitlement_grants', 1);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        P::gateway([]);
        $this->assertSame('paid', app(ResumeCheckout::class)->handle($buyer, $order->id)->state);
    }

    public function test_refund_before_any_settlement_and_reused_transaction_are_review_only(): void
    {
        $buyer = F::buyer();
        $refunded = F::reserve($buyer);
        $this->check($refunded, ['transaction_status' => 'refund']);
        $this->assertSame('review', $refunded->fresh()->state);
        $paid = F::reserve($buyer);
        $this->check($paid);
        $other = F::reserve($buyer);
        $this->check($other, ['transaction_id' => 'synthetic-'.$paid->id]);
        $this->assertSame('midtrans_transaction_reused', $other->fresh()->review_reason);
        $this->assertDatabaseCount('entitlement_grants', 1);
    }

    public function test_delayed_lapsed_payment_uses_authoritative_paid_time_and_does_not_unsuspend(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $this->travel(10)->days();
        $paidAt = now()->copy();
        $this->travel(2)->days();
        $buyer->forceFill(['suspended_at' => now()])->save();
        $this->check($order, ['settlement_time' => $paidAt->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s')]);
        $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($paidAt->addHours(720)));
        $this->assertSame('suspended', app(AccountPolicy::class)->state($buyer));
        $this->assertFalse(app(AccountPolicy::class)->allows($buyer, AccountAction::Mutate));
    }

    public function test_verified_renewal_unfreezes_only_open_selection_and_preserves_confirmation_and_deadline(): void
    {
        $project = $this->selectionProject();
        $path = '/g/'.$project->share_token.'/selection/';
        $order = F::reserve($project->photographer);
        $this->postJson($path.'choices', $this->selectionPayload($project))->assertOk();
        $this->travel(8)->days();
        $this->getJson($path.'data')->assertOk()->assertJsonPath('state.canWrite', false)->assertJsonPath('state.count', 1);
        $this->check($order);
        $this->getJson($path.'data')->assertOk()->assertJsonPath('state.canWrite', true)->assertJsonPath('state.count', 1);
        $this->postJson($path.'confirm', array_diff_key($this->selectionPayload($project), ['file_id' => true, 'selected' => true]))->assertOk();
        $second = F::reserve($project->photographer);
        $this->check($second);
        $this->getJson($path.'data')->assertOk()->assertJsonPath('state.canWrite', false)->assertJsonPath('state.locked', true);
        $project->modules()->where('type', 'selection')->update(['deadline' => now()]);
        $third = F::reserve($project->photographer);
        $this->check($third);
        $this->getJson($path.'data')->assertNotFound();
        $this->assertDatabaseCount('selection_confirmations', 1);
        $this->assertDatabaseCount('confirmation_choices', 1);
        $this->assertDatabaseCount('choices', 1);
    }

    public function test_transaction_failure_rolls_back_all_financial_effects_then_retry_recovers(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $end = $buyer->accountAccess->access_ends_at;
        $event = app(PaymentProcessing::class)->record($order, 'operator', queue: false);
        DB::unprepared("CREATE FUNCTION synthetic_payment_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'synthetic interruption'; END; $$;
            CREATE TRIGGER synthetic_payment_failure BEFORE INSERT ON voucher_redemptions FOR EACH ROW EXECUTE FUNCTION synthetic_payment_failure()");
        P::gateway([P::response($order), P::response($order)]);
        try {
            app(PaymentProcessing::class)->process($event->id);
            $this->fail('Injected ledger failure must abort.');
        } catch (QueryException) {
            $this->assertDatabaseCount('entitlement_grants', 0);
            $this->assertDatabaseCount('voucher_redemptions', 0);
            $this->assertTrue($buyer->fresh()->accountAccess->access_ends_at->eq($end));
            $this->assertNull($order->fresh()->applied_at);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
        } finally {
            DB::unprepared('DROP TRIGGER synthetic_payment_failure ON voucher_redemptions; DROP FUNCTION synthetic_payment_failure()');
        }
        app(PaymentProcessing::class)->process($event->id);
        $this->assertDatabaseCount('entitlement_grants', 1);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $this->assertSame('paid', $order->fresh()->state);
    }

    public function test_scheduler_recovers_missed_notification_and_stale_receipt_without_unbounded_outbox_growth(): void
    {
        $order = F::reserve(F::buyer());
        $this->travel(3)->minutes();
        app(PaymentRecovery::class)->sweep();
        app(PaymentRecovery::class)->sweep();
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('jobs', 1);
        $event = PaymentEvent::firstOrFail();
        $event->update(['state' => 'processing', 'started_at' => now(), 'attempts' => 1]);
        DB::table('jobs')->delete(); // Simulate a lost worker/queue entry after durable intake.
        $this->travel(6)->minutes();
        app(PaymentRecovery::class)->sweep();
        $this->assertDatabaseCount('jobs', 1);
        P::gateway([P::response($order)]);
        (new ProcessPaymentEvent($event->id))->handle(app(PaymentProcessing::class));
        $this->assertSame('paid', $order->fresh()->state);
        $this->assertDatabaseHas('payment_events', ['id' => $event->id, 'source' => 'scheduled', 'attempts' => 2, 'state' => 'processed']);
    }

    public function test_transient_retries_back_off_then_flag_review_without_releasing_capacity(): void
    {
        $order = F::reserve(F::buyer(), V::create(V::admin())->code);
        $event = app(PaymentProcessing::class)->record($order, 'operator', queue: false);
        P::gateway(array_fill(0, 5, new Response(503, [], '{}')));
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            app(PaymentProcessing::class)->process($event->id);
            $this->assertSame('retry', $event->fresh()->state);
            $this->assertTrue($event->fresh()->available_at->isFuture());
            $this->travelTo($event->fresh()->available_at);
        }
        app(PaymentProcessing::class)->process($event->id);
        $this->assertSame('review', $event->fresh()->state);
        $this->assertSame('midtrans_retries_exhausted', $order->fresh()->review_reason);
        $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
        $this->assertDatabaseCount('entitlement_grants', 0);
    }

    public function test_owner_recheck_is_authorized_audited_and_cannot_accept_paid_claims(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $url = '/admin/orders/'.$order->id.'/recheck';
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs($buyer)->postJson($url)->assertForbidden();
        $admin = V::admin();
        $this->actingAs($admin)->postJson($url, ['state' => 'paid'])->assertUnprocessable();
        $this->postJson($url)->assertAccepted()->assertExactJson(['queued' => true]);
        $this->assertDatabaseHas('payment_events', ['order_id' => $order->id, 'source' => 'admin', 'actor_id' => $admin->id]);
        $this->assertDatabaseCount('entitlement_grants', 0);
        $admin->forceFill(['suspended_at' => now()])->save();
        $this->postJson($url)->assertForbidden();
    }

    public function test_grant_redemption_processed_event_and_paid_order_are_immutable_in_postgres(): void
    {
        $order = F::reserve(F::buyer(), V::create(V::admin())->code);
        $event = $this->check($order);
        foreach ([fn () => DB::table('entitlement_grants')->where('order_id', $order->id)->update(['duration_days' => 60]),
            fn () => DB::table('entitlement_grants')->where('order_id', $order->id)->delete(),
            fn () => DB::table('voucher_redemptions')->delete(),
            fn () => $event->update(['source' => 'admin']),
            fn () => $order->fresh()->update(['state' => 'pending']),
            fn () => $order->fresh()->update(['paid_at' => now()->subDay()])] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Financial history must be immutable.');
            } catch (QueryException) {
                $this->assertSame('paid', $order->fresh()->state);
            }
        }
    }
}
