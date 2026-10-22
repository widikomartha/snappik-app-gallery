<?php

namespace Tests\Integration;

use App\Actions\Billing\ResumeCheckout;
use App\Contracts\PaymentGateway;
use App\Integrations\Midtrans\SandboxGateway;
use App\Models\Order;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\VoucherFixture as V;
use Tests\TestCase;

class MidtransCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        F::configure();
    }

    private function gateway(array $responses): SandboxGateway
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $gateway = new SandboxGateway(new Client(['handler' => $stack]), 'synthetic-test-secret', 'synthetic-merchant');
        app()->instance(PaymentGateway::class, $gateway);

        return $gateway;
    }

    private function page(string $token = 'synthetic-token'): Response
    {
        return new Response(201, [], json_encode(['token' => $token, 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$token]));
    }

    private function statusResponse(Order $order, array $overrides = []): Response
    {
        return new Response(200, [], json_encode([...['status_code' => '200', 'order_id' => $order->provider_order_id,
            'merchant_id' => $order->merchant_id, 'currency' => 'IDR', 'gross_amount' => $order->payable_idr.'.00',
            'payment_type' => 'qris', 'transaction_status' => 'pending'], ...$overrides]));
    }

    public function test_snap_request_is_sandbox_qris_only_and_uses_exact_persisted_identity_and_amount(): void
    {
        $order = F::reserve(F::buyer(), V::create(V::admin())->code);
        $gateway = $this->gateway([$this->page()]);
        $page = $gateway->create($order);
        $request = $this->history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v1/transactions', (string) $request->getUri());
        $this->assertSame(['order_id' => $order->provider_order_id, 'gross_amount' => 26100], $body['transaction_details']);
        $this->assertSame(['other_qris'], $body['enabled_payments']);
        $this->assertSame(24, $body['expiry']['duration']);
        $this->assertSame('Basic '.base64_encode('synthetic-test-secret:'), $request->getHeaderLine('Authorization'));
        $this->assertSame('synthetic-token', $page->token);
        $this->assertArrayNotHasKey('customer_details', $body);
        $this->assertArrayNotHasKey('recurring', $body);
    }

    public function test_timeout_then_core_missing_recreates_only_same_identity_and_keeps_hold_until_recovered(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer, V::create(V::admin(), ['total_limit' => 1])->code);
        $this->gateway([new ConnectException('Synthetic secret transport details', new Request('POST', 'https://example.test')),
            new Response(404, [], '{"status_code":"404"}'), $this->page()]);
        $resume = app(ResumeCheckout::class);
        $this->assertSame('unknown', $resume->handle($buyer, $order->id)->state);
        $this->assertSame('midtrans_response_unknown', $order->fresh()->failure);
        $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
        $this->assertSame('pending', $resume->handle($buyer, $order->id)->state);
        $this->assertCount(3, $this->history);
        $this->assertSame(['POST', 'GET', 'POST'], array_map(fn ($h) => $h['request']->getMethod(), $this->history));
        $bodies = array_map(fn ($i) => json_decode((string) $this->history[$i]['request']->getBody(), true), [0, 2]);
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('voucher_reservations', 1);
        $this->assertNull($order->fresh()->failure);
    }

    public function test_crash_after_creation_claim_reconciles_before_any_recreation(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $order->update(['state' => 'creating']);
        $this->gateway([$this->statusResponse($order)]);
        $result = app(ResumeCheckout::class)->handle($buyer, $order->id);
        $this->assertSame('pending', $result->state);
        $this->assertNull($result->snap_token);
        $this->assertCount(1, $this->history);
        $this->assertSame('GET', $this->history[0]['request']->getMethod());
        $this->assertFalse($buyer->accountAccess->has_paid_access);
    }

    public function test_pending_resume_and_core_404_reuse_stored_snap_page_without_post(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $this->gateway([$this->page(), new Response(404, [], '{"status_code":"404"}')]);
        $resume = app(ResumeCheckout::class);
        $first = $resume->handle($buyer, $order->id);
        $second = $resume->handle($buyer, $order->id);
        $this->assertSame($first->snap_token, $second->snap_token);
        $this->assertSame('pending', $second->state);
        $this->assertCount(2, $this->history);
    }

    public function test_failed_status_or_mismatch_never_recreates_releases_or_grants(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer, V::create(V::admin())->code);
        $order->update(['state' => 'unknown']);
        $bad = [['gross_amount' => '1.00'], ['currency' => 'USD'], ['order_id' => 'another-order'],
            ['merchant_id' => 'another-merchant'], ['payment_type' => 'credit_card'], ['transaction_status' => 'surprise']];
        $responses = [new Response(503, [], 'not-json'), ...array_map(fn ($data) => $this->statusResponse($order, $data), $bad)];
        $this->gateway($responses);
        foreach ($responses as $_) {
            $this->assertSame('unknown', app(ResumeCheckout::class)->handle($buyer, $order->id)->state);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
        }
        $this->assertSame(['GET'], array_values(array_unique(array_map(fn ($h) => $h['request']->getMethod(), $this->history))));
        $this->assertDatabaseCount('voucher_redemptions', 0);
        $this->assertFalse($buyer->accountAccess->has_paid_access);
    }

    public function test_local_expiry_and_terminal_provider_observations_keep_reservations_for_task011(): void
    {
        $buyer = F::buyer();
        foreach (['settlement', 'expire', 'cancel', 'deny', 'refund'] as $status) {
            $order = F::reserve($buyer, V::create(V::admin(), ['code' => 'SYNTHETIC-'.strtoupper($status)])->code);
            $order->update(['state' => 'unknown']);
            $this->gateway([$this->statusResponse($order, ['transaction_status' => $status])]);
            $this->assertSame('review', app(ResumeCheckout::class)->handle($buyer, $order->id)->state);
            $this->assertDatabaseHas('voucher_reservations', ['order_id' => $order->id, 'state' => 'held']);
        }
        $order = F::reserve($buyer);
        $order->update(['state' => 'unknown']);
        $this->travel(24)->hours();
        $this->gateway([new Response(404, [], '{"status_code":"404"}')]);
        $this->assertSame('unknown', app(ResumeCheckout::class)->handle($buyer, $order->id)->state);
        $this->assertSame('GET', $this->history[array_key_last($this->history)]['request']->getMethod());
        $this->assertDatabaseCount('voucher_redemptions', 0);
        $this->assertFalse($buyer->accountAccess->has_paid_access);
    }

    public function test_untrusted_redirects_malformed_responses_and_duplicate_errors_remain_unknown(): void
    {
        $buyer = F::buyer();
        foreach ([new Response(201, [], '{"token":"synthetic","redirect_url":"https://example.test/steal"}'),
            new Response(201, [], 'malformed'), new Response(400, [], '{"error_messages":["duplicate order"]}')] as $response) {
            $order = F::reserve($buyer);
            $this->gateway([$response]);
            $this->assertSame('unknown', app(ResumeCheckout::class)->handle($buyer, $order->id)->state);
            $this->assertNull($order->fresh()->checkout_url);
        }
    }

    public function test_merchant_configuration_change_cannot_send_an_old_order_to_another_account(): void
    {
        $buyer = F::buyer();
        $order = F::reserve($buyer);
        $this->gateway([]);
        config(['billing.merchant_id' => 'other-synthetic-merchant']);
        $this->assertSame('unknown', app(ResumeCheckout::class)->handle($buyer, $order->id)->state);
        $this->assertCount(0, $this->history);
    }
}
