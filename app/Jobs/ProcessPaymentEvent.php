<?php

namespace App\Jobs;

use App\Services\Billing\PaymentProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public string $eventId)
    {
        $this->onConnection('payments')->onQueue('payments');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(PaymentProcessing $payments): void
    {
        try {
            $payments->process($this->eventId);
        } catch (\Throwable) {
            // Framework failed-job diagnostics must never retain raw provider/SQL data.
            throw new \RuntimeException('payment_processing_interrupted');
        }
    }
}
