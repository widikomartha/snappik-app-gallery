<?php

namespace App\Console\Commands;

use App\Services\Operations\ReleaseReadiness;
use Illuminate\Console\Command;

final class CheckRelease extends Command
{
    protected $signature = 'snappik:release-check {--evidence= : Private JSON evidence record for this build and origin} {--json}';

    protected $description = 'Read release prerequisites without contacting providers, changing access or authorizing publication';

    public function handle(ReleaseReadiness $readiness): int
    {
        $checks = $readiness->checks($this->option('evidence') ?: null);
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => ! in_array(false, $checks, true), 'checks' => $checks], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $name => $passed) {
                $this->line(($passed ? 'PASS ' : 'BLOCKED ').$name);
            }
            $this->line('Evidence entries are operator attestations, not automated provider verification or publication permission.');
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
