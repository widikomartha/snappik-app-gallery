<?php

namespace App\Console\Commands;

use App\Services\Archives\ArchiveRecovery;
use Illuminate\Console\Command;

class RecoverArchives extends Command
{
    protected $signature = 'snappik:archives-maintain';

    protected $description = 'Fail abandoned ZIP attempts, expire output and retry scoped private archive cleanup';

    public function handle(ArchiveRecovery $recovery): int
    {
        $recovery->sweep();
        $this->info('Archive maintenance completed; pending cleanup remains eligible for the next run.');

        return self::SUCCESS;
    }
}
