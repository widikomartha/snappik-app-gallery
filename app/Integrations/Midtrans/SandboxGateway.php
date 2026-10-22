<?php

namespace App\Integrations\Midtrans;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Services\Billing\GatewayCheckout;
use App\Services\ProviderFailure;
use GuzzleHttp\ClientInterface;

final class SandboxGateway implements PaymentGateway
{
    public function __construct(private ClientInterface $http, #[\SensitiveParameter] private string $serverKey, private string $merchantId) {}

    private function request(Order $order, string $method, string $url, array $options = []): array
    {
        if ($this->serverKey === '' || $this->merchantId === '' || $order->environment !== 'sandbox' || $order->merchant_id !== $this->merchantId) {
            throw new ProviderFailure('midtrans_configuration_mismatch');
        }
        try {
            $response = $this->http->request($method, $url, [...$options, 'auth' => [$this->serverKey, ''],
                'headers' => ['Accept' => 'application/json'], 'connect_timeout' => 5, 'timeout' => 20,
                'http_errors' => false, 'allow_redirects' => false]);
            $data = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ProviderFailure('midtrans_response_unknown');
        }
        if (! is_array($data)) {
            throw new ProviderFailure('midtrans_response_unknown');
        }

        return [$response->getStatusCode(), $data];
    }

    public function create(Order $order): GatewayCheckout
    {
        [$http, $data] = $this->request($order, 'POST', 'https://app.sandbox.midtrans.com/snap/v1/transactions', ['json' => [
            'transaction_details' => ['order_id' => $order->provider_order_id, 'gross_amount' => $order->payable_idr],
            'enabled_payments' => ['other_qris'],
            'expiry' => ['start_time' => $order->created_at->format('Y-m-d H:i:s O'), 'unit' => 'hours', 'duration' => 24],
            'page_expiry' => ['unit' => 'hours', 'duration' => 24],
        ]]);
        $token = $data['token'] ?? null;
        $url = $data['redirect_url'] ?? null;
        if (! in_array($http, [200, 201], true) || ! is_string($token) || ! preg_match('/\A[a-zA-Z0-9-]{1,128}\z/', $token)
            || $url !== 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$token) {
            // Even a rejected repeat may follow an earlier accepted request. Keep the hold.
            throw new ProviderFailure('midtrans_creation_unknown', $http);
        }

        return new GatewayCheckout($token, $url);
    }

    public function status(Order $order): string
    {
        [$http, $data] = $this->request($order, 'GET', 'https://api.sandbox.midtrans.com/v2/'.rawurlencode($order->provider_order_id).'/status');
        if ($http === 404 && ($data['status_code'] ?? null) === '404') {
            return 'missing';
        }
        if ($http !== 200 || ! in_array($data['status_code'] ?? null, ['200', '201'], true)
            || ($data['order_id'] ?? null) !== $order->provider_order_id || ($data['merchant_id'] ?? null) !== $order->merchant_id
            || ($data['currency'] ?? null) !== 'IDR' || ($data['payment_type'] ?? null) !== 'qris'
            || ! is_string($data['gross_amount'] ?? null) || ! preg_match('/\A'.preg_quote((string) $order->payable_idr, '/').'(?:\.00)?\z/', $data['gross_amount'])
            || ! in_array($data['transaction_status'] ?? null, ['pending', 'settlement', 'deny', 'cancel', 'expire', 'refund', 'partial_refund', 'failure'], true)) {
            throw new ProviderFailure('midtrans_status_unverified');
        }

        return $data['transaction_status'];
    }
}
