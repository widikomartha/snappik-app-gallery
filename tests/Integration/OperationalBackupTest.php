<?php

namespace Tests\Integration;

use App\Contracts\BackupStorage;
use App\Services\Operations\DatabaseBackup;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BackupTools;
use Tests\TestCase;

class OperationalBackupTest extends TestCase
{
    public function test_pipe_failure_and_storage_failure_cannot_mark_success_and_streaming_stays_bounded(): void
    {
        if (getenv('SNAPPIK_BACKUP_PROOF') !== '1') {
            $this->markTestSkipped('Opt in with SNAPPIK_BACKUP_PROOF=1; requires Bash and GPG.');
        }
        $tools = new BackupTools;
        $destination = new class implements BackupStorage
        {
            public int $calls = 0;

            public bool $fail = false;

            public function store(string $key, string $path): void
            {
                $this->calls++;
                if ($this->fail) {
                    throw new \RuntimeException('synthetic-storage-secret');
                }
            }
        };
        $this->app->instance(BackupStorage::class, $destination);
        try {
            $script = $tools->root.'/dump.sh';
            file_put_contents($script, "#!/bin/sh\nprintf 'partial synthetic dump'\nexit 12\n");
            chmod($script, 0700);
            config(['backup.pg_dump' => BackupTools::posix($script)]);
            $this->artisan('snappik:backup')->assertFailed();
            $this->assertSame(0, $destination->calls, 'pipefail must reject even successfully encrypted partial dumps.');
            $this->assertNull(Cache::get('operations:backup'));
            $this->assertTrue(Cache::get('operations:backup_failed'));
            $this->assertSame([], glob(storage_path('app/private/backup-spool/encrypted-*')));

            // Larger than a typical PHP memory budget, with no plaintext spool or output buffer.
            file_put_contents($script, "#!/bin/sh\nhead -c 83886080 /dev/zero\n");
            $start = memory_get_usage(true);
            $destination->fail = true;
            $this->artisan('snappik:backup')->doesntExpectOutputToContain('synthetic-storage-secret')->assertFailed();
            $this->assertNull(Cache::get('operations:backup'));
            $this->assertSame([], glob(storage_path('app/private/backup-spool/encrypted-*')));
            $destination->fail = false;
            app(DatabaseBackup::class)->run($destination);
            $this->assertSame(2, $destination->calls);
            $this->assertSame(now()->timestamp, Cache::get('operations:backup'));
            $this->assertNull(Cache::get('operations:backup_failed'));
            $growth = memory_get_peak_usage(true) - $start;
            $this->assertLessThan(32 * 1024 ** 2, $growth);
            $this->assertSame([], glob(storage_path('app/private/backup-spool/encrypted-*')));
            fwrite(STDOUT, "\nBACKUP_STREAM_PROFILE ".json_encode(['input_bytes' => 83886080, 'peak_php_growth_bytes' => $growth, 'partial_rejected' => true, 'spool_clean' => true])."\n");
        } finally {
            $tools->cleanup();
        }
    }
}
