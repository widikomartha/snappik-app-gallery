<?php

namespace App\Services\Drive;

use App\Actions\Projects\ManageProject;
use App\Contracts\DriveSource;
use App\Models\CatalogueRevision;
use App\Models\GalleryModule;
use App\Models\Project;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use App\Services\ProviderFailure;
use Illuminate\Support\Facades\DB;

class ImportProjectCatalogue
{
    public function __construct(private DriveSource $drive, private ManageProject $projects, private AccountPolicy $accounts) {}

    /** Returns false only when another worker owns this revision's session lock. */
    public function handle(string $id): bool
    {
        $lock = 'catalogue:'.$id;
        $connection = DB::connection();
        if (! $connection->selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$lock])->acquired) {
            return false;
        }
        try {
            $revision = CatalogueRevision::findOrFail($id);
            if (! in_array($revision->state, ['queued', 'importing'], true)) {
                return true;
            }
            try {
                $this->current($revision);
                $revision->update(['state' => 'importing', 'failure' => null]);
                if (! $revision->entries()->exists()) {
                    $source = $revision->source;
                    $root = $this->drive->metadata(new DriveReference($source['id'], $source['resourceKey']));
                    if ($root->mimeType !== DriveItem::FOLDER) {
                        throw new ProviderFailure('drive_source_is_not_folder');
                    }
                    $snapshot = $root->snapshot();
                    $snapshot['resourceKey'] ??= $source['resourceKey'];
                    $revision->entries()->create(['file_id' => $root->reference->id, 'kind' => 'folder',
                        'name' => $root->name, 'path' => '', 'metadata' => $snapshot]);
                }
                while ($folder = $revision->entries()->where('kind', 'folder')->where('listed', false)->orderBy('id')->first()) {
                    $this->current($revision);
                    $item = DriveItem::fromArray($folder->metadata);
                    if ($this->drive->metadata($item->reference)->mimeType !== DriveItem::FOLDER) {
                        throw new ProviderFailure('drive_folder_changed');
                    }
                    $page = $this->drive->children($item->reference, $folder->page_token);
                    DB::transaction(function () use ($revision, $folder, $page) {
                        $digest = hash('sha256', $folder->page_token ?? '');
                        if (DB::table('catalogue_pages')->where('entry_id', $folder->id)->where('token_digest', $digest)->exists()) {
                            throw new ProviderFailure('drive_pagination_cycle');
                        }
                        DB::table('catalogue_pages')->insert(['entry_id' => $folder->id, 'token_digest' => $digest]);
                        foreach ($page->items as $child) {
                            if ($revision->entries()->where('file_id', $child->reference->id)->exists()) {
                                throw new ProviderFailure('drive_repeated_identity_or_cycle');
                            }
                            $kind = match (true) {
                                $child->mimeType === DriveItem::FOLDER => 'folder',
                                $child->mimeType === DriveItem::SHORTCUT => 'shortcut',
                                $child->isPhoto() => 'photo',
                                default => 'unsupported',
                            };
                            $revision->entries()->create(['file_id' => $child->reference->id, 'kind' => $kind,
                                'name' => $child->name, 'parent_file_id' => $folder->file_id,
                                'path' => $folder->path === '' ? $child->name : $folder->path.'/'.$child->name,
                                'metadata' => $child->snapshot(), 'bytes' => $child->size]);
                        }
                        if ($page->nextPageToken !== null && DB::table('catalogue_pages')->where('entry_id', $folder->id)
                            ->where('token_digest', hash('sha256', $page->nextPageToken))->exists()) {
                            throw new ProviderFailure('drive_pagination_cycle');
                        }
                        $folder->update(['page_token' => $page->nextPageToken, 'listed' => $page->nextPageToken === null]);
                    });
                }
                if (! $revision->entries()->where('kind', 'photo')->exists()) {
                    throw new ProviderFailure('drive_no_supported_photos');
                }
                $digest = hash_init('sha256');
                foreach ($revision->entries()->where('kind', 'photo')->orderBy('file_id')->lazy(500) as $entry) {
                    hash_update($digest, json_encode([$entry->file_id, $entry->path, $entry->metadata], JSON_THROW_ON_ERROR)."\n");
                }
                $this->publish($revision, hash_final($digest));
            } catch (\Throwable $error) {
                // Never persist/log arbitrary exception messages, source URLs or keys.
                $reason = $error instanceof ProviderFailure ? $error->reason : 'catalogue_import_failed';
                $revision->update(['state' => $reason === 'catalogue_superseded' ? 'superseded' : 'failed', 'failure' => $reason]);
            }

            return true;
        } finally {
            $connection->selectOne('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
        }
    }

    private function current(CatalogueRevision $revision): GalleryModule
    {
        $module = GalleryModule::findOrFail($revision->module_id);
        $project = $module->project()->firstOrFail();
        if ($module->requested_revision_id !== $revision->id || $module->source !== $revision->source) {
            throw new ProviderFailure('catalogue_superseded');
        }
        if ($project->archived_at || ! $this->accounts->allows($project->photographer()->firstOrFail(), AccountAction::Mutate)) {
            throw new ProviderFailure('catalogue_access_closed');
        }

        return $module;
    }

    private function publish(CatalogueRevision $revision, string $digest): void
    {
        $module = $this->current($revision);
        $project = $module->project()->firstOrFail();
        $this->projects->locked($project->photographer()->firstOrFail(), $project->id, function ($owner, Project $locked) use ($revision, $digest) {
            $module = $this->current($revision);
            $previous = CatalogueRevision::find($module->published_revision_id);
            if ($module->type === 'selection' && $locked->selection_locked_at && $previous && $previous->source !== $revision->source) {
                throw new ProviderFailure('catalogue_selection_locked');
            }
            foreach ($locked->modules()->where('id', '!=', $module->id)->get() as $other) {
                $overlap = $other->published_revision_id && DB::table('catalogue_entries as incoming')
                    ->join('catalogue_entries as existing', 'incoming.file_id', '=', 'existing.file_id')
                    ->where('incoming.revision_id', $revision->id)->where('existing.revision_id', $other->published_revision_id)
                    ->whereIn('incoming.kind', ['photo', 'folder'])->whereIn('existing.kind', ['photo', 'folder'])->exists();
                if ($overlap || ($other->source && $revision->entries()->where('file_id', $other->source['id'])->where('kind', 'folder')->exists())) {
                    throw new ProviderFailure('catalogue_sources_overlap');
                }
            }
            // All pages and the pointer change are committed before any reader can see the revision.
            $revision->update(['state' => 'complete', 'manifest_digest' => $digest, 'completed_at' => now()]);
            $module->update(['published_revision_id' => $revision->id]);
        });
    }
}
