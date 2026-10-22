<?php

namespace App\Services\Drive;

use App\Services\ProviderFailure;
use Illuminate\Support\Facades\Cache;

final class DriveRequestPacer
{
    private string $key;

    public function __construct(
        #[\SensitiveParameter] string $apiKey,
        private int $intervalMilliseconds = 1000,
        private ?\Closure $clock = null,
        private ?\Closure $sleep = null,
    ) {
        if ($intervalMilliseconds < 100 || $intervalMilliseconds > 10000) {
            throw new ProviderFailure('drive_request_interval_invalid');
        }
        // Coordinate only the same server key. Never cache the credential itself.
        $this->key = 'drive-request-pacing:'.hash('sha256', $apiKey);
    }

    public function wait(): void
    {
        try {
            // Database cache supports cross-process locks; no Redis is needed.
            // Hold the lock through the wait so concurrent workers cannot reserve
            // the same start time. Lock lifetime exceeds the maximum interval.
            Cache::lock($this->key.':lock', 15)->block(15, function (): void {
                $now = $this->time();
                $next = (float) Cache::get($this->key, 0);
                if ($next > $now) {
                    ($this->sleep ?? usleep(...))((int) ceil(min(10, $next - $now) * 1000000));
                }
                Cache::put($this->key, $this->time() + $this->intervalMilliseconds / 1000, 60);
            });
        } catch (\Throwable) {
            // A failed cache/lock must not silently turn off provider pacing.
            throw new ProviderFailure('drive_request_pacing_unavailable', retryable: true);
        }
    }

    private function time(): float
    {
        return $this->clock === null ? microtime(true) : ($this->clock)();
    }
}
