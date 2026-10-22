<?php

namespace App\Services\Archives;

use App\Jobs\BuildDeliveryArchive;
use App\Models\ArchiveEntry;
use App\Models\ArchiveJob;
use App\Models\CatalogueEntry;
use App\Models\CatalogueRevision;
use App\Models\GalleryModule;
use App\Models\Project;
use App\Services\Access\AccountAction;
use App\Services\Access\DeliveryAccess;
use App\Services\Access\GalleryGate;
use App\Services\Access\SelectionAccess;
use App\Services\Drive\DriveItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryArchives
{
    public function __construct(private DeliveryAccess $access, private SelectionAccess $capabilities, private GalleryGate $gates) {}

    /** Project lock serializes with publication/gate changes and concurrent requests. */
    public function authorized(Request $request, #[\SensitiveParameter] string $capability, \Closure $work): mixed
    {
        return DB::transaction(function () use ($request, $capability, $work) {
            $project = $this->capabilities->resolve($capability);
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $module = $this->access->module($request, $capability, AccountAction::Download);

            return $work($module);
        });
    }

    public function current(GalleryModule $module): ?ArchiveJob
    {
        $job = ArchiveJob::where('module_id', $module->id)->where('revision_id', $module->published_revision_id)
            ->where('gate_version', $module->gate_version)->lockForUpdate()->first();
        if ($job?->state === 'ready' && ! $job->expires_at?->isFuture()) {
            $job->update(['state' => 'expired']);
        }

        return $job;
    }

    public function request(GalleryModule $module): ArchiveJob
    {
        $job = $this->current($module);
        if ($job && in_array($job->state, ['queued', 'processing', 'ready'], true)) {
            return $job;
        }
        if (! $job) {
            $revision = CatalogueRevision::whereKey($module->published_revision_id)->where('module_id', $module->id)->where('state', 'complete')->firstOrFail();
            $job = ArchiveJob::create(['module_id' => $module->id, 'revision_id' => $revision->id,
                'gate_version' => $module->gate_version, 'manifest_digest' => $revision->manifest_digest]);
            $hash = hash_init('sha256');
            $count = $bytes = $pathBytes = 0;
            foreach (CatalogueEntry::where('revision_id', $revision->id)->where('kind', 'photo')->orderBy('path')->orderBy('file_id')->lazy(200) as $photo) {
                $item = DriveItem::fromArray($photo->metadata);
                $parts = explode('/', $photo->path);
                array_pop($parts);
                $parent = implode('/', array_map(ArchivePath::component(...), $parts));
                $path = ArchivePath::append($parent, $item->name, hash('sha256', $item->reference->id), true);
                $snapshot = $item->snapshot();
                hash_update($hash, json_encode([$item->reference->id, $path, $snapshot], JSON_THROW_ON_ERROR)."\n");
                ArchiveEntry::create(['archive_job_id' => $job->id, 'file_id' => $item->reference->id,
                    'path' => $path, 'bytes' => $item->size, 'metadata' => $snapshot]);
                $count++;
                $bytes += $item->size;
                $pathBytes += strlen($path);
            }
            abort_if($count === 0, 422, 'Belum ada foto untuk ZIP.');
            $job->update(['manifest_digest' => hash_final($hash), 'total_files' => $count, 'total_bytes' => $bytes, 'path_bytes' => $pathBytes]);
        }
        $job->update(['state' => 'queued', 'failure' => null, 'lease_until' => null, 'attempt_id' => null,
            'object_key' => null, 'zip_bytes' => null, 'ready_at' => null, 'expires_at' => null,
            'completed_files' => 0, 'completed_bytes' => 0]);
        BuildDeliveryArchive::dispatch($job->id)->onConnection('archives')->onQueue('archives');

        return $job;
    }

    public function eligible(ArchiveJob $job): bool
    {
        $module = GalleryModule::find($job->module_id);
        if (! $module || $module->type !== 'delivery' || $module->published_revision_id !== $job->revision_id
            || $module->gate_version !== $job->gate_version) {
            return false;
        }
        $project = $module->project()->firstOrFail();

        // Workers do not inherit a browser session. Only persisted generation and gallery gates govern a build.
        return $this->gates->allows($project->photographer()->firstOrFail(), AccountAction::Download, true,
            $module->published, $project->archived_at !== null, $module->deadline, false, true,
            $module->gate_version, $module->gate_version);
    }

    public function state(?ArchiveJob $job): ?array
    {
        if (! $job) {
            return null;
        }

        return ['id' => $job->id, 'state' => $job->state, 'files' => $job->total_files, 'bytes' => $job->total_bytes,
            'completedFiles' => $job->completed_files, 'completedBytes' => $job->completed_bytes,
            'expiresAt' => $job->expires_at?->toIso8601String(), 'failure' => $job->failure];
    }

    /** Session advisory locks survive transactions and are released by PostgreSQL on process death. */
    public function exclusive(string $id, \Closure $work): bool
    {
        $key = 'archive:'.$id;
        if (! DB::selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$key])->acquired) {
            return false;
        }
        try {
            $work();
        } finally {
            DB::selectOne('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$key]);
        }

        return true;
    }
}
