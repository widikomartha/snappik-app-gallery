<?php

namespace App\Services\Billing;

use App\Actions\Billing\ApplyPayment;
use App\Contracts\PaymentGateway;
use App\Jobs\ProcessPaymentEvent;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Services\ProviderFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentProcessing
{
    public function record(Order $order, string $source, ?string $digest = null, ?int $actor = null, bool $queue = true): PaymentEvent
    {
        return DB::transaction(function () use ($order, $source, $digest, $actor, $queue) {
            $id = (string) Str::uuid();
            $inserted = DB::table('payment_events')->insertOrIgnore(['id' => $id, 'order_id' => $order->id, 'source' => $source,
                'receipt_digest' => $digest, 'actor_id' => $actor, 'available_at' => now(), 'enqueued_at' => $queue ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
            $event = $inserted ? PaymentEvent::findOrFail($id) : PaymentEvent::where('receipt_digest', $digest)->firstOrFail();
            if ($queue && $inserted) {
                // This database queue is the outbox: receipt and job commit together.
                ProcessPaymentEvent::dispatch($event->id);
            }

            return $event;
        });
    }

    public function process(string $id, bool $alreadyLocked = false): ?GatewayStatus
    {
        $event = PaymentEvent::findOrFail($id);
        $lock = 'checkout:'.$event->order_id;
        if (! $alreadyLocked && ! DB::selectOne('select pg_try_advisory_lock(hashtextextended(?, 0)) as acquired', [$lock])->acquired) {
            return null; // Durable receipt remains due; the scheduler will retry it.
        }
        try {
            $event->refresh();
            if (in_array($event->state, ['processed', 'review'], true) || $event->available_at->isFuture()) {
                return null;
            }
            if ($event->attempts >= 5) {
                app(ApplyPayment::class)->handle($id, null, 'midtrans_retries_exhausted');

                return null;
            }
            $event->update(['state' => 'processing', 'started_at' => now(), 'attempts' => $event->attempts + 1]);
            $order = Order::findOrFail($event->order_id);
            try {
                $status = app(PaymentGateway::class)->status($order);
            } catch (ProviderFailure $e) {
                app(ApplyPayment::class)->handle($id, null, $e->reason, $e->retryable);

                return null;
            }
            app(ApplyPayment::class)->handle($id, $status);

            return $status;
        } finally {
            if (! $alreadyLocked) {
                DB::select('select pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
            }
        }
    }
}
