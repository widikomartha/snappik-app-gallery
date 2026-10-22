<?php

namespace App\Services\Archives;

use App\Contracts\DeliveryArchiveStorage;
use App\Models\ArchiveJob;
use Illuminate\Support\Facades\DB;

class ArchiveRecovery
{
    public function __construct(private DeliveryArchives $archives) {}

    public function sweep(): void
    {
        foreach (ArchiveJob::where('state', 'processing')->where('lease_until', '<=', now())->orderBy('id')->lazyById(100) as $job) {
            $this->archives->exclusive($job->id, function () use ($job) {
                ArchiveJob::whereKey($job->id)->where('state', 'processing')->where('lease_until', '<=', now())
                    ->update(['state' => 'failed', 'failure' => 'archive_worker_interrupted', 'lease_until' => null]);
            });
        }
        ArchiveJob::where('state', 'ready')->where('expires_at', '<=', now())->update(['state' => 'expired']);
        foreach (DB::table('archive_attempts')->where('cleanup_pending', true)->orderBy('id')->lazyById(100) as $attempt) {
            $this->archives->exclusive($attempt->archive_job_id, fn () => $this->cleanAttempt($attempt->id));
        }
    }

    public function cleanAttempt(string $id): void
    {
        $attempt = DB::table('archive_attempts')->where('id', $id)->where('cleanup_pending', true)->first();
        if (! $attempt) {
            return;
        }
        $job = ArchiveJob::findOrFail($attempt->archive_job_id);
        if ($job->attempt_id === $id && ($job->state === 'processing'
            || ($job->state === 'ready' && $job->expires_at?->isFuture() && $this->archives->eligible($job)))) {
            return;
        }
        if ($job->attempt_id === $id && $job->state === 'ready') {
            // A recreation can commit while storage maintenance is deciding what to
            // remove. Never overwrite a newer queued generation with a stale model.
            ArchiveJob::whereKey($job->id)->where('attempt_id', $id)->where('state', 'ready')
                ->update(['state' => $job->expires_at?->isFuture() ? 'failed' : 'expired', 'failure' => 'archive_gallery_closed']);
        }
        try {
            app(DeliveryArchiveStorage::class)->cleanup($attempt->object_key);
            DB::table('archive_attempts')->where('id', $id)->update(['cleanup_pending' => false, 'cleanup_failure' => null, 'updated_at' => now()]);
        } catch (\Throwable) {
            DB::table('archive_attempts')->where('id', $id)->update(['cleanup_failure' => 'archive_cleanup_required', 'updated_at' => now()]);
        }
    }
}
