<?php

namespace App\Services\Drive;

use App\Contracts\DriveSource;
use App\Models\DriveProofRun;
use App\Models\User;
use App\Services\Archives\ArchivePath;
use App\Services\ProviderFailure;
use Illuminate\Support\Facades\DB;

final class ImportDriveProof
{
    public function __construct(private DriveSource $drive) {}

    public function start(User $owner, DriveReference $reference): DriveProofRun
    {
        abort_unless(app()->environment('local', 'testing'), 403);
        abort_unless(User::whereKey($owner->id)->whereNull('suspended_at')->exists(), 403);
        $root = $this->drive->metadata($reference);
        if ($root->mimeType !== DriveItem::FOLDER) {
            throw new ProviderFailure('drive_source_is_not_folder');
        }
        // Preserve the configured resource key if a response omits it.
        $snapshot = $root->snapshot();
        $snapshot['resourceKey'] ??= $reference->resourceKey;

        return DB::transaction(function () use ($owner, $snapshot, $root) {
            $run = DriveProofRun::create(['user_id' => $owner->id, 'state' => 'importing', 'root' => $snapshot]);
            $run->nodes()->create(['file_id' => $root->reference->id, 'kind' => 'folder', 'path' => '', 'metadata' => $snapshot]);

            return $run;
        });
    }

    public function resume(DriveProofRun $run, int $ownerId): void
    {
        $run = $run->fresh();
        abort_unless(app()->environment('local', 'testing') && $run && (int) $run->user_id === $ownerId && User::whereKey($ownerId)->whereNull('suspended_at')->exists(), 403);
        $connection = DB::connection();
        $lock = 'drive-proof:'.$run->id;
        if (! $connection->selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$lock])->acquired) {
            throw new ProviderFailure('drive_import_already_running');
        }
        try {
            $this->traverse($run->refresh());
        } finally {
            $connection->selectOne('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
        }
    }

    private function traverse(DriveProofRun $run): void
    {
        if (! in_array($run->state, ['importing', 'import_failed'], true)) {
            throw new ProviderFailure('drive_import_not_resumable');
        }
        $run->update(['state' => 'importing', 'failure' => null]);
        try {
            while ($node = $run->nodes()->where('kind', 'folder')->where('listed', false)->orderBy('id')->first()) {
                $item = DriveItem::fromArray($node->metadata);
                // An inaccessible descendant cannot be silently skipped.
                if ($this->drive->metadata($item->reference)->mimeType !== DriveItem::FOLDER) {
                    throw new ProviderFailure('drive_folder_changed');
                }
                $page = $this->drive->children($item->reference, $node->page_token);
                DB::transaction(function () use ($run, $node, $page) {
                    $locked = $run->nodes()->whereKey($node->id)->lockForUpdate()->firstOrFail();
                    if ($locked->listed || $locked->page_token !== $node->page_token) {
                        throw new ProviderFailure('drive_checkpoint_conflict');
                    }
                    $digest = hash('sha256', $node->page_token ?? '');
                    if (DB::table('drive_proof_pages')->where('node_id', $node->id)->where('token_digest', $digest)->exists()) {
                        throw new ProviderFailure('drive_pagination_cycle');
                    }
                    DB::table('drive_proof_pages')->insert(['node_id' => $node->id, 'token_digest' => $digest]);
                    foreach ($page->items as $child) {
                        if ($run->nodes()->where('file_id', $child->reference->id)->exists()) {
                            throw new ProviderFailure('drive_repeated_identity_or_cycle');
                        }
                        $kind = match (true) {
                            $child->mimeType === DriveItem::FOLDER => 'folder',
                            $child->mimeType === DriveItem::SHORTCUT => 'shortcut',
                            $child->isPhoto() => 'photo',
                            default => 'unsupported',
                        };
                        $run->nodes()->create(['file_id' => $child->reference->id, 'kind' => $kind,
                            'path' => ArchivePath::append($node->path, $child->name, $child->reference->id, $kind === 'photo'),
                            'metadata' => $child->snapshot(), 'bytes' => $child->size]);
                    }
                    if ($page->nextPageToken !== null && DB::table('drive_proof_pages')->where('node_id', $node->id)->where('token_digest', hash('sha256', $page->nextPageToken))->exists()) {
                        throw new ProviderFailure('drive_pagination_cycle');
                    }
                    $locked->update(['page_token' => $page->nextPageToken, 'listed' => $page->nextPageToken === null]);
                });
            }
            if (! $run->nodes()->where('kind', 'photo')->exists()) {
                throw new ProviderFailure('drive_no_supported_photos');
            }
            $run->update(['state' => 'imported']);
        } catch (\Throwable $error) {
            $failure = $error instanceof ProviderFailure ? $error : new ProviderFailure('drive_import_failed');
            $run->update(['state' => 'import_failed', 'failure' => $failure->reason]);
            throw $failure;
        }
    }
}
