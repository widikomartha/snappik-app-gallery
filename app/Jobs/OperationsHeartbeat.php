<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

final class OperationsHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(public readonly string $channel, public readonly int $queuedAt) {}

    public function handle(): void
    {
        if (! in_array($this->channel, ['catalogues', 'archives', 'payments'], true)) {
            return;
        }
        // Use dispatch time: delayed probes must not hide a stopped scheduler or backlog.
        $key = 'operations:worker:'.$this->channel;
        Cache::lock($key.':lock', 10)->block(5, function () use ($key) {
            if ($this->queuedAt > (int) Cache::get($key, 0)) {
                Cache::put($key, $this->queuedAt, 86400);
            }
        });
    }
}
