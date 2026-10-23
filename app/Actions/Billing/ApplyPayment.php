<?php

namespace App\Actions\Billing;

use App\Models\AccountAccess;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Billing\GatewayStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplyPayment
{
    // This is the only financial writer. Both webhook and reconciliation use it.
    public function handle(string $eventId, ?GatewayStatus $status, ?string $failure = null, bool $retryable = false): void
    {
        $receipt = PaymentEvent::findOrFail($eventId);
        $owner = Order::findOrFail($receipt->order_id)->user_id;
        DB::transaction(function () use ($receipt, $owner, $status, $failure, $retryable) {
            User::whereKey($owner)->lockForUpdate()->firstOrFail();
            // A paid contract is honored even when the owner has since been suspended.
            DB::table('account_access')->insertOrIgnore(['user_id' => $owner, 'created_at' => now(), 'updated_at' => now()]);
            $access = AccountAccess::whereKey($owner)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($receipt->order_id)->lockForUpdate()->firstOrFail();
            if ($order->voucher_id !== null) {
                Voucher::whereKey($order->voucher_id)->lockForUpdate()->firstOrFail();
            }
            $reservation = DB::table('voucher_reservations')->where('order_id', $order->id)->lockForUpdate()->first();
            $event = PaymentEvent::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if (in_array($event->state, ['processed', 'review'], true)) {
                return;
            }
            $review = $failure !== null && ! $retryable ? $failure : null;
            $updates = ['last_checked_at' => now(), 'failure' => $failure];
            if ($status !== null) {
                if ($order->provider_transaction_id !== null && $status->transactionId !== null
                    && $order->provider_transaction_id !== $status->transactionId) {
                    $review = 'midtrans_transaction_mismatch';
                } elseif ($status->transactionId !== null && Order::where('merchant_id', $order->merchant_id)
                    ->where('environment', $order->environment)->where('provider_transaction_id', $status->transactionId)
                    ->where('id', '<>', $order->id)->exists()) {
                    $review = 'midtrans_transaction_reused';
                } elseif ($status->state === 'settlement') {
                    if (! $status->paidAt || ! $status->transactionId) {
                        $review = 'midtrans_settlement_unverified';
                    } elseif ($order->applied_at === null) {
                        if ($order->voucher_id !== null && ! $reservation) {
                            throw new \RuntimeException('payment_reservation_missing');
                        }
                        $start = $access->access_ends_at && $access->access_ends_at->gt($status->paidAt)
                            ? $access->access_ends_at : $status->paidAt;
                        $end = $start->addHours($order->duration_days * 24);
                        DB::table('entitlement_grants')->insert(['order_id' => $order->id, 'user_id' => $owner,
                            'payment_event_id' => $event->id, 'paid_at' => $status->paidAt, 'starts_at' => $start,
                            'ends_at' => $end, 'duration_days' => $order->duration_days, 'created_at' => now()]);
                        DB::table('account_access')->where('user_id', $owner)->update(['access_ends_at' => $end,
                            'has_paid_access' => true, 'updated_at' => now()]);
                        if ($reservation) {
                            if ($reservation->state === 'released') {
                                $review = 'late_settlement_after_release';
                            }
                            DB::table('voucher_redemptions')->insert(['reservation_id' => $reservation->id, 'created_at' => now()]);
                            DB::table('voucher_reservations')->where('id', $reservation->id)->update(['state' => 'consumed', 'resolved_at' => now()]);
                        }
                        $updates += ['state' => 'paid', 'provider_state' => 'settlement', 'paid_at' => $status->paidAt,
                            'applied_at' => now(), 'provider_transaction_id' => $status->transactionId];
                    }
                } elseif (in_array($status->state, ['deny', 'cancel', 'expire', 'failure'], true)) {
                    if ($order->applied_at === null) {
                        if ($reservation && $reservation->state === 'held') {
                            DB::table('voucher_reservations')->where('id', $reservation->id)->update(['state' => 'released', 'resolved_at' => now()]);
                        }
                        $updates += ['state' => 'failed', 'provider_state' => $status->state, 'provider_transaction_id' => $status->transactionId];
                    }
                } elseif ($status->state === 'pending') {
                    if ($order->applied_at === null) {
                        if ($order->state === 'failed') {
                            $review = 'midtrans_terminal_contradiction';
                        } else {
                            $updates += ['state' => 'pending', 'provider_state' => 'pending', 'provider_transaction_id' => $status->transactionId];
                        }
                    }
                } elseif ($status->state === 'missing') {
                    // Core 404 does not prove that Snap creation failed or was cancelled.
                    if ($order->applied_at === null && $order->state !== 'failed') {
                        $updates += ['state' => $order->snap_token !== null ? 'pending' : 'unknown'];
                    }
                } else {
                    $review = in_array($status->state, ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true)
                        ? 'midtrans_reversal_review' : 'midtrans_unknown_state';
                }
            } elseif ($order->applied_at === null && $order->state !== 'failed') {
                $updates += ['state' => $review ? 'review' : 'unknown'];
            }
            if ($review !== null) {
                $updates['review_reason'] = $review;
                if ($order->applied_at === null && $order->state !== 'failed' && ($updates['state'] ?? null) !== 'paid') {
                    $updates['state'] = 'review';
                }
            }
            $order->update($updates);
            $event->update(['state' => $review ? 'review' : ($retryable ? 'retry' : 'processed'),
                'provider_state' => $status?->state, 'result_digest' => $status?->digest, 'failure' => $review ?? $failure,
                'available_at' => now()->addSeconds(min(3600, 30 * (2 ** min($event->attempts, 6)))),
                'processed_at' => $retryable && ! $review ? null : now()]);
            if ($review !== null) {
                DB::afterCommit(fn () => Log::warning('payment_review_required', [
                    'order_id' => $order->id, 'event_id' => $event->id, 'reason' => $review,
                ]));
            }
        }, 3);
    }
}
