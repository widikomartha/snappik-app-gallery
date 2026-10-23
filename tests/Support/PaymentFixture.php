<?php

namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\Integrations\Midtrans\SandboxGateway;
use App\Models\Order;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class PaymentFixture
{
    public static function body(Order $order, array $overrides = []): array
    {
        return [...['order_id' => $order->provider_order_id, 'merchant_id' => $order->merchant_id,
            'gross_amount' => $order->payable_idr.'.00', 'currency' => 'IDR', 'payment_type' => 'qris',
            'status_code' => '200', 'transaction_status' => 'settlement', 'transaction_id' => 'synthetic-'.$order->id,
            'settlement_time' => now('Asia/Jakarta')->format('Y-m-d H:i:s'), 'fraud_status' => 'accept'], ...$overrides];
    }

    public static function signed(Order $order, array $overrides = []): array
    {
        $data = self::body($order, $overrides);
        $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].'synthetic-test-secret');

        return $data;
    }

    public static function response(Order $order, array $overrides = []): Response
    {
        return new Response(200, [], json_encode(self::body($order, $overrides), JSON_THROW_ON_ERROR));
    }

    public static function gateway(array $responses): SandboxGateway
    {
        $gateway = new SandboxGateway(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]),
            'synthetic-test-secret', 'synthetic-merchant');
        app()->instance(PaymentGateway::class, $gateway);

        return $gateway;
    }
}
