<?php

namespace Tests\Support;

use App\Actions\Auth\VerifyAccount;
use App\Actions\Billing\Checkout;
use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\User;
use App\Services\Billing\GatewayCheckout;
use Illuminate\Support\Str;

final class CheckoutFixture
{
    public static function configure(): void
    {
        config(['billing.merchant_id' => 'synthetic-merchant', 'billing.server_key' => 'synthetic-test-secret', 'billing.qris_minimum' => 1]);
    }

    public static function buyer(): User
    {
        $user = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($user);

        return $user->fresh();
    }

    public static function reserve(User $buyer, ?string $code = null): Order
    {
        $checkout = app(Checkout::class);

        return $checkout->reserve($buyer, (string) Str::uuid(), $checkout->quote($buyer, $code)['quote_token']);
    }

    public static function gateway(): PaymentGateway
    {
        $gateway = new class implements PaymentGateway
        {
            public int $creates = 0;

            public int $checks = 0;

            public string $nextStatus = 'missing';

            public function create(Order $order): GatewayCheckout
            {
                $this->creates++;

                return new GatewayCheckout('synthetic-snap-token', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/synthetic-snap-token');
            }

            public function status(Order $order): string
            {
                $this->checks++;

                return $this->nextStatus;
            }
        };
        app()->instance(PaymentGateway::class, $gateway);

        return $gateway;
    }
}
