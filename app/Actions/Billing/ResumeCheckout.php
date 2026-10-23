<?php

namespace App\Actions\Billing;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Billing\PaymentProcessing;
use App\Services\ProviderFailure;
use Illuminate\Support\Facades\DB;

class ResumeCheckout
{
    public function __construct(private Checkout $checkout, private PaymentGateway $gateway, private PaymentProcessing $payments) {}

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
            if (in_array($order->state, ['paid', 'failed'], true)) {
                return $order;
            }
            if ($order->environment !== 'sandbox' || $order->merchant_id !== (string) config('billing.merchant_id')) {
                return $this->unknown($order, 'midtrans_configuration_mismatch');
            }
            try {
                if ($order->state !== 'created') {
                    $event = $this->payments->record($order, 'resume', queue: false);
                    $status = $this->payments->process($event->id, alreadyLocked: true);
                    $order->refresh();
                    if ($status?->state !== 'missing') {
                        return $order;
                    }
                    // A Core 404 is not cancellation. Reuse the saved Snap page when possible.
                    if ($order->snap_token !== null || $order->checkout_expires_at->isPast()) {
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
