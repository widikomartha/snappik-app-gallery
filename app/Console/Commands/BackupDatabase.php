<?php

namespace App\Console\Commands;

use App\Contracts\BackupStorage;
use App\Services\Operations\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class BackupDatabase extends Command
{
    protected $signature = 'snappik:backup';

    protected $description = 'Stream an encrypted PostgreSQL backup to the independently configured private backup bucket';

    public function handle(DatabaseBackup $backup, BackupStorage $destination): int
    {
        try {
            $backup->run($destination);
            $this->info('Encrypted backup stored and upload metadata verified. Isolated restore remains required.');

            return self::SUCCESS;
        } catch (\Throwable) {
            try {
                Cache::put('operations:backup_failed', true, 7 * 86400);
            } catch (\Throwable) {
                // Keep even a cache outage from exposing a raw database error.
            }
            // Subprocess/SDK exceptions can contain credentials or connection strings.
            $this->error('Backup failed. Check private configuration, tools, public encryption key and destination.');

            return self::FAILURE;
        }
    }
}
