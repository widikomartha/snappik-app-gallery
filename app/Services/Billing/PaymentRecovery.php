<?php

namespace App\Services\Billing;

use App\Jobs\ProcessPaymentEvent;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;

class PaymentRecovery
{
    public function sweep(): void
    {
        // A missed notification is recovered by status lookup, never by a local expiry guess.
        Order::whereNull('review_reason')->where('created_at', '<=', now()->subMinutes(2))
            ->where(function ($q) {
                $q->whereNull('last_checked_at')->orWhere(function ($q) {
                    $q->whereIn('state', ['created', 'creating', 'unknown', 'pending'])->where('last_checked_at', '<=', now()->subMinutes(5));
                })->orWhere('last_checked_at', '<=', now()->subDay());
            })->select('id')->chunkById(100, function ($orders) {
                foreach ($orders as $item) {
                    DB::transaction(function () use ($item) {
                        $order = Order::whereKey($item->id)->lockForUpdate()->firstOrFail();
                        if (! PaymentEvent::where('order_id', $order->id)->whereIn('state', ['pending', 'processing', 'retry'])->exists()) {
                            app(PaymentProcessing::class)->record($order, 'scheduled');
                        }
                    });
                }
            });
        PaymentEvent::whereIn('state', ['pending', 'processing', 'retry'])->where('available_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('enqueued_at')->orWhere('enqueued_at', '<=', now()->subMinutes(5)))
            ->where(fn ($q) => $q->where('state', '<>', 'processing')->orWhere('started_at', '<=', now()->subMinutes(5)))
            ->select('id')->chunkById(100, function ($events) {
                foreach ($events as $item) {
                    DB::transaction(function () use ($item) {
                        $event = PaymentEvent::whereKey($item->id)->lockForUpdate()->firstOrFail();
                        if (! in_array($event->state, ['processed', 'review'], true)
                            && ($event->enqueued_at === null || $event->enqueued_at->lte(now()->subMinutes(5)))) {
                            $event->update(['enqueued_at' => now()]);
                            ProcessPaymentEvent::dispatch($event->id);
                        }
                    });
                }
            });
    }
}
