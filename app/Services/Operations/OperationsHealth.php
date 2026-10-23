<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class OperationsHealth
{
    public function checks(): array
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = true;
            $checks['payment_review_clear'] = ! DB::table('orders')->whereNotNull('review_reason')->exists();
            $checks['failed_queue_clear'] = ! DB::table('failed_jobs')->exists();
        } catch (\Throwable) {
            $checks['database'] = false;
            $checks['payment_review_clear'] = false;
            $checks['failed_queue_clear'] = false;
        }
        foreach (['scheduler', 'worker:catalogues', 'worker:archives', 'worker:payments'] as $component) {
            $checks[str_replace(':', '_', $component)] = $this->fresh('operations:'.$component, (int) config('release.heartbeat_seconds'));
        }
        $checks['backup_recent'] = $this->fresh('operations:backup', (int) config('release.backup_seconds'));
        try {
            $checks['backup_last_attempt'] = Cache::get('operations:backup_failed', false) === false;
        } catch (\Throwable) {
            $checks['backup_last_attempt'] = false;
        }

        return $checks;
    }

    private function fresh(string $key, int $seconds): bool
    {
        try {
            $at = Cache::get($key);

            return is_int($at) && $at <= now()->timestamp && now()->timestamp - $at < $seconds;
        } catch (\Throwable) {
            return false;
        }
    }
}
