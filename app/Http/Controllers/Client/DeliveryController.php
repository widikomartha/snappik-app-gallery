<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CatalogueEntry;
use App\Services\Access\AccountAction;
use App\Services\Access\DeliveryAccess;
use App\Services\Drive\DeliveryMedia;
use App\Services\Drive\DriveItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    public function show(Request $request, string $capability, DeliveryAccess $access)
    {
        $access->module($request, $capability);

        return Inertia::render('client/delivery');
    }

    public function data(Request $request, string $capability, DeliveryAccess $access)
    {
        $input = $request->validate(['page' => 'sometimes|integer|min:1', 'folder_page' => 'sometimes|integer|min:1', 'folder' => 'nullable|integer|min:1']);
        $module = $access->module($request, $capability);
        $entries = CatalogueEntry::where('revision_id', $module->published_revision_id);
        $folder = empty($input['folder']) ? null : (clone $entries)->where('kind', 'folder')->findOrFail($input['folder']);
        $photos = (clone $entries)->where('kind', 'photo')->select(['id', 'name', 'path'])->orderBy('id');
        if ($folder) {
            $photos->whereRaw('file_id IN (WITH RECURSIVE subtree AS (
                SELECT file_id FROM catalogue_entries WHERE id = ? AND revision_id = ?
                UNION ALL SELECT c.file_id FROM catalogue_entries c JOIN subtree s ON c.parent_file_id = s.file_id
                WHERE c.revision_id = ?
            ) SELECT file_id FROM subtree)', [$folder->id, $module->published_revision_id, $module->published_revision_id]);
        }

        return response()->json(['revision' => $module->published_revision_id, 'photos' => $photos->paginate(36),
            'folders' => (clone $entries)->where('kind', 'folder')->whereNotNull('parent_file_id')->select(['id', 'path'])->orderBy('id')->paginate(20, ['*'], 'folder_page'),
            'folder' => $folder?->path, 'legacyFolders' => (clone $entries)->where('kind', 'photo')->whereNull('parent_file_id')->exists()]);
    }

    public function media(Request $request, string $capability, int $entry, DeliveryAccess $access, DeliveryMedia $media)
    {
        $request->validate(['thumbnail' => 'sometimes|boolean', 'download' => 'sometimes|boolean']);
        $module = $access->module($request, $capability, AccountAction::Download);
        $photo = CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->findOrFail($entry);

        return $media->response($request, DriveItem::fromArray($photo->metadata),
            $request->boolean('thumbnail') && ! $request->boolean('download'), $request->boolean('download'));
    }
}
