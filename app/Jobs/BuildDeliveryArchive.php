<?php

namespace App\Jobs;

use App\Models\ArchiveEntry;
use App\Models\ArchiveJob;
use App\Models\GalleryModule;
use App\Models\Project;
use App\Services\Archives\ArchiveRecovery;
use App\Services\Archives\BuildStreamingZip;
use App\Services\Archives\DeliveryArchives;
use App\Services\Drive\DriveItem;
use App\Services\ProviderFailure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BuildDeliveryArchive implements ShouldQueue
{
    use Queueable;

    public $tries = 0;

    public $timeout = 0;

    public function __construct(public readonly string $archiveId) {}

    public function handle(DeliveryArchives $archives): void
    {
        if (! $archives->exclusive($this->archiveId, function () use ($archives) {
            $job = ArchiveJob::find($this->archiveId);
            if (! $job || $job->state !== 'queued') {
                return;
            }
            if (! $archives->eligible($job)) {
                $job->update(['state' => 'failed', 'failure' => 'archive_gallery_closed']);

                return;
            }
            $attempt = (string) Str::uuid();
            $key = 'archives/'.$job->id.'/'.$attempt.'.zip';
            DB::transaction(function () use ($job, $attempt, $key) {
                DB::table('archive_attempts')->insert(['id' => $attempt, 'archive_job_id' => $job->id, 'object_key' => $key,
                    'cleanup_pending' => true, 'created_at' => now(), 'updated_at' => now()]);
                $job->update(['state' => 'processing', 'attempt_id' => $attempt, 'object_key' => $key, 'lease_until' => now()->addMinutes(15)]);
            });
            $lastPulse = 0;
            $pulse = function (bool $force = false) use ($archives, $job, &$lastPulse) {
                if (! $force && microtime(true) - $lastPulse < 10) {
                    return;
                }
                if (! $archives->eligible($job)) {
                    throw new ProviderFailure('archive_gallery_closed');
                }
                $job->update(['lease_until' => now()->addMinutes(15)]);
                $lastPulse = microtime(true);
            };
            try {
                // Resolve external adapters after the durable claim so missing provider
                // configuration is a visible failed attempt, rather than an endless queued job.
                $builder = app(BuildStreamingZip::class);
                $entries = (function () use ($job, $pulse) {
                    $hash = hash_init('sha256');
                    foreach (ArchiveEntry::where('archive_job_id', $job->id)->orderBy('id')->lazyById(200) as $entry) {
                        $pulse(true);
                        hash_update($hash, json_encode([$entry->file_id, $entry->path, $entry->metadata], JSON_THROW_ON_ERROR)."\n");
                        yield ['path' => $entry->path, 'item' => DriveItem::fromArray($entry->metadata)];
                    }
                    if (! hash_equals($job->manifest_digest, hash_final($hash))) {
                        throw new ProviderFailure('archive_manifest_mismatch');
                    }
                })();
                $result = $builder->build($key, $entries, $job->total_files, $job->total_bytes,
                    $job->total_bytes + 2 * $job->path_bytes + $job->total_files * 256 + 1024,
                    fn (int $files, int $bytes) => $job->update(['completed_files' => $files, 'completed_bytes' => $bytes]), $pulse);
                DB::transaction(function () use ($archives, $job, $result) {
                    $module = GalleryModule::findOrFail($job->module_id);
                    Project::whereKey($module->project_id)->sharedLock()->firstOrFail();
                    if (! $archives->eligible($job)) {
                        throw new ProviderFailure('archive_gallery_closed');
                    }
                    $ready = now();
                    $job->update(['state' => 'ready', 'zip_bytes' => $result['zip_bytes'], 'ready_at' => $ready,
                        'expires_at' => $ready->copy()->addHours(24), 'lease_until' => null]);
                });
            } catch (\Throwable $error) {
                $job->update(['state' => 'failed', 'lease_until' => null,
                    'failure' => $error instanceof ProviderFailure ? $error->reason : 'archive_attempt_failed']);
                // Cleanup remains durable even if the worker is killed here or storage is unavailable.
                app(ArchiveRecovery::class)->cleanAttempt($attempt);
            }
        })) {
            $this->release(30);
        }
    }
}
