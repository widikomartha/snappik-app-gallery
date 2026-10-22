<?php

namespace App\Actions\Billing;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\ProviderFailure;
use Illuminate\Support\Facades\DB;

class ResumeCheckout
{
    public function __construct(private Checkout $checkout, private PaymentGateway $gateway) {}

    public function handle(User $user, string $id): Order
    {
        // Session lock survives short transactions, but is released on process death.
        // The persisted 'creating' state then requires status recovery before POST.
        DB::transaction(fn () => $this->checkout->owned($user, $id, AccountAction::Checkout));
        $lock = 'checkout:'.$id;
        $acquired = DB::selectOne('select pg_try_advisory_lock(hashtextextended(?, 0)) as acquired', [$lock])->acquired;
        if (! $acquired) {
            return DB::transaction(fn () => $this->checkout->owned($user, $id));
        }
        try {
            $order = DB::transaction(fn () => $this->checkout->owned($user, $id, AccountAction::Checkout));
            if ($order->state === 'review') {
                return $order;
            }
            if ($order->environment !== 'sandbox' || $order->merchant_id !== (string) config('billing.merchant_id')) {
                return $this->unknown($order, 'midtrans_configuration_mismatch');
            }
            try {
                if ($order->state !== 'created') {
                    $status = $this->gateway->status($order);
                    if ($status !== 'missing') {
                        $order->update(['provider_state' => $status, 'state' => $status === 'pending' ? 'pending' : 'review',
                            'last_checked_at' => now(), 'failure' => null]);

                        return $order;
                    }
                    // A Core 404 is not cancellation. Reuse the saved Snap page when possible.
                    if ($order->snap_token !== null || $order->checkout_expires_at->isPast()) {
                        $order->update(['state' => $order->snap_token !== null ? 'pending' : 'unknown', 'failure' => null, 'last_checked_at' => now()]);

                        return $order;
                    }
                }
                if ($order->checkout_expires_at->lte(now())) {
                    return $this->unknown($order, 'midtrans_creation_window_elapsed');
                }
                $order->update(['state' => 'creating', 'failure' => null]);
                $page = $this->gateway->create($order);
                $order->update(['snap_token' => $page->token, 'checkout_url' => $page->url, 'state' => 'pending', 'last_checked_at' => now()]);
            } catch (ProviderFailure $e) {
                return $this->unknown($order, $e->reason);
            }

            return $order;
        } finally {
            DB::select('select pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
        }
    }

    private function unknown(Order $order, string $reason): Order
    {
        $order->update(['state' => 'unknown', 'failure' => $reason, 'last_checked_at' => now()]);

        return $order;
    }
}
