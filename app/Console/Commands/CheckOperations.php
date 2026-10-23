<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationsHealth;
use Illuminate\Console\Command;

final class CheckOperations extends Command
{
    protected $signature = 'snappik:operations-check {--json}';

    protected $description = 'Check local database, scheduler, workers, backup age and review alarms without exposing private data';

    public function handle(OperationsHealth $health): int
    {
        $checks = $health->checks();
        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $name => $passed) {
                $this->line(($passed ? 'PASS ' : 'FAIL ').$name);
            }
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
