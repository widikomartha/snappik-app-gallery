<?php

namespace App\Console\Commands;

use App\Jobs\OperationsHeartbeat as Probe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class OperationsHeartbeat extends Command
{
    protected $signature = 'snappik:operations-heartbeat';

    protected $description = 'Record scheduler execution and enqueue probes on each product worker';

    public function handle(): int
    {
        $at = now()->timestamp;
        foreach (['catalogues', 'archives', 'payments'] as $channel) {
            Probe::dispatch($channel, $at)->onConnection($channel)->onQueue($channel);
        }
        Cache::put('operations:scheduler', $at, 86400);
        $this->info('Operations probes queued.');

        return self::SUCCESS;
    }
}
