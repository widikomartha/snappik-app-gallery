<?php

namespace App\Console\Commands;

use App\Contracts\ArchiveStorage;
use App\Jobs\BuildDriveProofArchive;
use App\Models\DriveProofRun;
use App\Models\User;
use App\Services\Archives\BuildStreamingZip;
use App\Services\Archives\VerifyDriveProof;
use App\Services\Drive\DriveReference;
use App\Services\Drive\ImportDriveProof;
use App\Services\ProviderFailure;
use Illuminate\Console\Command;

class DriveProof extends Command
{
    protected $signature = 'snappik:drive-proof {--owner= : Existing local test user ID} {--resume= : Resume a run owned by that user} {--queue : Queue ZIP after import} {--verify : Verify an existing ready run} {--cleanup : Delete the private object of an existing run}';

    protected $description = 'Local/test-only authorized public Drive → private R2 integration proof';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('This prototype runs only in local/testing environments.');

            return self::FAILURE;
        }
        if (! ctype_digit((string) $this->option('owner')) || ! ($owner = User::find($this->option('owner')))) {
            $this->error('An existing local test user is required via --owner.');

            return self::FAILURE;
        }
        // Preflight without echoing values, building objects, or making provider requests.
        if (($this->option('verify') && $this->option('cleanup'))
            || (($this->option('verify') || $this->option('cleanup')) && (! $this->option('resume') || $this->option('queue')))) {
            $this->error('Use --resume with --verify or --cleanup, without --queue.');

            return self::FAILURE;
        }
        $required = ['r2.account_id', 'r2.access_key_id', 'r2.secret_access_key', 'r2.bucket'];
        if (! $this->option('cleanup')) {
            $required[] = 'api_key';
        }
        if (! $this->option('resume')) {
            $required[] = 'folder_url';
        }
        foreach ($required as $field) {
            if (! is_string(config('drive_proof.'.$field)) || config('drive_proof.'.$field) === '') {
                $this->error('Integration blocked: configure drive_proof.'.$field.' in the ignored environment.');

                return self::FAILURE;
            }
        }
        $started = microtime(true);
        try {
            $importer = app(ImportDriveProof::class);
            $run = $this->option('resume')
                ? DriveProofRun::where('user_id', $owner->id)->whereKey($this->option('resume'))->firstOrFail()
                : $importer->start($owner, DriveReference::fromFolderUrl(config('drive_proof.folder_url')));
            $this->info('Run: '.$run->id);
            if ($this->option('cleanup')) {
                if (! in_array($run->state, ['ready', 'zip_failed', 'expired'], true)) {
                    throw new ProviderFailure('archive_cleanup_not_claimable');
                }
                if ($run->object_key !== null) {
                    app(ArchiveStorage::class)->delete($run->object_key);
                }
                $run->update(['state' => 'expired']);
                $this->info('Private proof object deleted.');

                return self::SUCCESS;
            }
            if ($this->option('verify')) {
                $this->info(json_encode(app(VerifyDriveProof::class)->verify($run, $owner->id)));

                return self::SUCCESS;
            }
            if (in_array($run->state, ['importing', 'import_failed'], true)) {
                $importer->resume($run, $owner->id);
            }
            $run->refresh();
            $this->line('Manifest: '.json_encode($run->nodes()->selectRaw('kind, count(*) as count')->groupBy('kind')->pluck('count', 'kind')));
            if ($this->option('queue')) {
                if (! in_array($run->state, ['imported', 'zip_failed'], true)) {
                    throw new ProviderFailure('archive_attempt_not_claimable');
                }
                BuildDriveProofArchive::dispatch($run->id)->onQueue('drive-proof');
                $this->info('ZIP queued on drive-proof.');
            } else {
                (new BuildDriveProofArchive($run->id))->handle(app(BuildStreamingZip::class));
                $run->refresh();
                $this->info(json_encode(['state' => $run->state, 'files' => $run->completed_files, 'source_bytes' => $run->completed_bytes,
                    'zip_bytes' => $run->zip_bytes, 'seconds' => round(microtime(true) - $started, 3), 'peak_php_bytes' => memory_get_peak_usage(true)]));
                $this->info(json_encode(app(VerifyDriveProof::class)->verify($run, $owner->id)));
            }

            return self::SUCCESS;
        } catch (\Throwable $error) {
            // Sanitized diagnostic only, including on unexpected SDK/framework errors.
            $this->error($error instanceof ProviderFailure ? $error->getMessage() : 'drive_proof_failed');

            return self::FAILURE;
        }
    }
}
