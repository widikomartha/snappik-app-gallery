<?php

namespace App\Services\Operations;

use App\Contracts\BackupStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class DatabaseBackup
{
    public function run(BackupStorage $destination): void
    {
        if (! config('backup.enabled') || ! BackupConfiguration::valid() || config('database.default') !== 'pgsql') {
            throw new \RuntimeException('Backup configuration is unavailable.');
        }
        Cache::lock('operations:backup:lock', 7200)->block(1, function () use ($destination) {
            $directory = storage_path('app/private/backup-spool');
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new \RuntimeException('Backup spool is unavailable.');
            }
            $path = tempnam($directory, 'encrypted-');
            if ($path === false) {
                throw new \RuntimeException('Backup spool is unavailable.');
            }
            chmod($path, 0600);
            try {
                $this->dump($path);
                if (filesize($path) === 0) {
                    throw new \RuntimeException('Backup output is empty.');
                }
                $destination->store('backups/'.now('UTC')->format('Y-m-d').'/'.Str::uuid().'.dump.gpg', $path);
                Cache::put('operations:backup', now()->timestamp, 7 * 86400);
                Cache::forget('operations:backup_failed');
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        });
    }

    private function dump(string $path): void
    {
        $db = DB::connection('pgsql')->getConfig();
        // Static shell program, all variable data passed through quoted environment values.
        // pipefail prevents a failed dump from publishing a valid encryption of partial input.
        $script = 'set -euo pipefail; umask 077; "$SNAPPIK_DUMP_BIN" --no-password --format=custom --no-owner --no-acl 2>/dev/null | "$SNAPPIK_GPG_BIN" --no-options --homedir "$SNAPPIK_GPG_HOME" --batch --yes --no-auto-key-locate --no-auto-key-retrieve --trust-model always --cipher-algo AES256 --recipient "$SNAPPIK_RECIPIENT" --encrypt --output "$SNAPPIK_ENCRYPTED_SPOOL" 2>/dev/null';
        $process = new Process([(string) config('backup.bash'), '-c', $script], base_path(), [
            'PGHOST' => $db['host'], 'PGPORT' => (string) $db['port'], 'PGUSER' => $db['username'],
            'PGPASSWORD' => $db['password'] ?? '', 'PGDATABASE' => $db['database'], 'PGSSLMODE' => $db['sslmode'] ?? 'prefer',
            'SNAPPIK_DUMP_BIN' => config('backup.pg_dump'), 'SNAPPIK_GPG_BIN' => config('backup.gpg'),
            'SNAPPIK_GPG_HOME' => config('backup.gpg_home'), 'SNAPPIK_RECIPIENT' => config('backup.recipient'),
            'SNAPPIK_ENCRYPTED_SPOOL' => PHP_OS_FAMILY === 'Windows'
                ? preg_replace_callback('/\A([a-zA-Z]):\//', fn ($matches) => '/'.strtolower($matches[1]).'/', str_replace('\\', '/', $path))
                : $path,
        ], timeout: 3600);
        $process->disableOutput();
        if ($process->run() !== 0) {
            throw new \RuntimeException('Encrypted database backup failed.');
        }
    }
}
