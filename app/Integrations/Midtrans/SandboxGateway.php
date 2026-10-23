<?php

namespace App\Integrations\Midtrans;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Services\Billing\GatewayCheckout;
use App\Services\Billing\GatewayStatus;
use App\Services\ProviderFailure;
use Carbon\CarbonImmutable;
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
            throw new ProviderFailure('midtrans_response_unknown', retryable: true);
        }
        if (! is_array($data)) {
            throw new ProviderFailure('midtrans_response_unknown', retryable: true);
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

    public function status(Order $order): GatewayStatus
    {
        [$http, $data] = $this->request($order, 'GET', 'https://api.sandbox.midtrans.com/v2/'.rawurlencode($order->provider_order_id).'/status');
        if ($http === 404 && ($data['status_code'] ?? null) === '404') {
            return new GatewayStatus('missing');
        }
        if ($http === 429 || $http >= 500) {
            throw new ProviderFailure('midtrans_status_unavailable', $http, true);
        }
        if ($http !== 200 || ! in_array($data['status_code'] ?? null, ['200', '201', '202'], true)
            || ($data['order_id'] ?? null) !== $order->provider_order_id || ($data['merchant_id'] ?? null) !== $order->merchant_id
            || ($data['currency'] ?? null) !== 'IDR' || ($data['payment_type'] ?? null) !== 'qris'
            || ! is_string($data['gross_amount'] ?? null) || ! preg_match('/\A'.preg_quote((string) $order->payable_idr, '/').'(?:\.00)?\z/', $data['gross_amount'])
            || ! is_string($data['transaction_status'] ?? null) || ! preg_match('/\A[a-z_]{1,32}\z/', $data['transaction_status'])
            || ! is_string($data['transaction_id'] ?? null) || ! preg_match('/\A[a-zA-Z0-9-]{1,128}\z/', $data['transaction_id'])) {
            throw new ProviderFailure('midtrans_status_unverified');
        }
        $paidAt = null;
        if ($data['transaction_status'] === 'settlement') {
            if (($data['status_code'] ?? null) !== '200' || (isset($data['fraud_status']) && $data['fraud_status'] !== 'accept')) {
                throw new ProviderFailure('midtrans_settlement_unverified');
            }
            $time = $data['settlement_time'] ?? null;
            try {
                $paidAt = is_string($time) ? CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $time, 'Asia/Jakarta') : null;
            } catch (\Throwable) {
                $paidAt = null;
            }
            if (! $paidAt || $paidAt->format('Y-m-d H:i:s') !== $time || $paidAt->isFuture()
                || $paidAt->lt($order->created_at->copy()->startOfSecond())) {
                throw new ProviderFailure('midtrans_paid_time_unverified');
            }
            $paidAt = $paidAt->utc();
        }

        return new GatewayStatus($data['transaction_status'], $data['transaction_id'], $paidAt,
            hash('sha256', json_encode([$data['order_id'], $data['merchant_id'], $data['currency'], $data['gross_amount'],
                $data['payment_type'], $data['transaction_status'], $data['transaction_id'], $paidAt?->toIso8601String()], JSON_THROW_ON_ERROR)));
    }

    // Notification status is only a wake-up signal. Never use it as payment proof.
    public function verifyNotification(Order $order, #[\SensitiveParameter] array $data): bool
    {
        if ($this->serverKey === '' || $this->merchantId === '' || $order->environment !== 'sandbox'
            || $order->merchant_id !== $this->merchantId) {
            return false;
        }
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'merchant_id', 'currency', 'payment_type', 'transaction_status'] as $key) {
            if (! is_string($data[$key] ?? null) || strlen($data[$key]) > 256) {
                return false;
            }
        }

        return preg_match('/\A[0-9a-f]{128}\z/', $data['signature_key'])
            && preg_match('/\A[0-9]{3}\z/', $data['status_code'])
            && hash_equals(hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$this->serverKey), $data['signature_key'])
            && $data['order_id'] === $order->provider_order_id && $data['merchant_id'] === $order->merchant_id
            && $data['currency'] === 'IDR' && $data['payment_type'] === 'qris'
            && preg_match('/\A'.preg_quote((string) $order->payable_idr, '/').'(?:\.00)?\z/', $data['gross_amount'])
            && preg_match('/\A[a-z_]{1,32}\z/', $data['transaction_status']);
    }
}
