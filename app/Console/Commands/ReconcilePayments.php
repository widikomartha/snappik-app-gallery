<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Billing\PaymentProcessing;
use App\Services\Billing\PaymentRecovery;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ReconcilePayments extends Command
{
    protected $signature = 'snappik:payments-reconcile {--order= : Queue an authoritative recheck of one application order UUID}';

    protected $description = 'Recover payment receipts and queue sandbox status reconciliation; never manually mark paid';

    public function handle(PaymentRecovery $recovery, PaymentProcessing $payments): int
    {
        if ($id = $this->option('order')) {
            if (! Str::isUuid($id) || ! ($order = Order::find($id))) {
                $this->error('Order not found.');

                return self::FAILURE;
            }
            $payments->record($order, 'operator');
        } else {
            $recovery->sweep();
        }
        $this->info('Payment reconciliation queued. Orders flagged for review: '.Order::whereNotNull('review_reason')->count());

        return self::SUCCESS;
    }
}
