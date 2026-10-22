<?php

namespace App\Jobs;

use App\Models\DriveProofRun;
use App\Models\User;
use App\Services\Archives\BuildStreamingZip;
use App\Services\Drive\DriveItem;
use App\Services\ProviderFailure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BuildDriveProofArchive implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    // Prototype is operator-driven and disabled in production. Product gallery
    // authorization, leases, retries and deduplication belong to TASK-008.
    public function __construct(public readonly string $runId) {}

    public function handle(BuildStreamingZip $builder): void
    {
        abort_unless(app()->environment('local', 'testing'), 403);
        $run = DB::transaction(function () {
            $run = DriveProofRun::whereKey($this->runId)->lockForUpdate()->firstOrFail();
            abort_unless(User::whereKey($run->user_id)->exists(), 403);
            if (! in_array($run->state, ['imported', 'zip_failed'], true)) {
                throw new ProviderFailure('archive_attempt_not_claimable');
            }
            $run->update(['state' => 'processing', 'failure' => null, 'completed_files' => 0, 'completed_bytes' => 0,
                'object_key' => 'proofs/'.$run->id.'/'.Str::uuid().'.zip']);

            return $run;
        });
        try {
            $photos = $run->nodes()->where('kind', 'photo');
            $count = (clone $photos)->count();
            $bytes = (int) (clone $photos)->sum('bytes');
            $pathBytes = (int) (clone $photos)->sum(DB::raw('octet_length(path)'));
            $entries = (function () use ($photos) {
                foreach ($photos->orderBy('path')->orderBy('file_id')->lazy(200) as $node) {
                    yield ['path' => $node->path, 'item' => DriveItem::fromArray($node->metadata)];
                }
            })();
            $result = $builder->build($run->object_key, $entries, $count, $bytes, $bytes + 2 * $pathBytes + $count * 256 + 1024,
                fn (int $files, int $done) => $run->update(['completed_files' => $files, 'completed_bytes' => $done]));
            $ready = now();
            $run->update(['state' => 'ready', 'zip_bytes' => $result['zip_bytes'], 'ready_at' => $ready, 'expires_at' => $ready->copy()->addDay()]);
        } catch (\Throwable $error) {
            $failure = $error instanceof ProviderFailure ? $error : new ProviderFailure('archive_attempt_failed');
            $run->update(['state' => $failure->reason === 'archive_cleanup_required' ? 'cleanup_required' : 'zip_failed', 'failure' => $failure->reason]);
            throw $failure;
        }
    }
}
