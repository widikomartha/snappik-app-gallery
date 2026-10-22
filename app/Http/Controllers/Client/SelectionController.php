<?php

namespace App\Http\Controllers\Client;

use App\Actions\Selections\ManageSelection;
use App\Contracts\DrivePreviewSource;
use App\Contracts\DriveSource;
use App\Http\Controllers\Controller;
use App\Models\CatalogueEntry;
use App\Models\Project;
use App\Services\Access\SelectionAccess;
use App\Services\Drive\DriveItem;
use App\Services\ProviderFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SelectionController extends Controller
{
    public function show(Request $request, string $capability, SelectionAccess $access)
    {
        $access->module($request, $access->resolve($capability));

        return Inertia::render('client/selection');
    }

    public function data(Request $request, string $capability, SelectionAccess $access)
    {
        $input = $request->validate(['page' => 'sometimes|integer|min:1', 'folder_page' => 'sometimes|integer|min:1',
            'folder' => 'nullable|integer|min:1', 'review' => 'sometimes|boolean']);

        return DB::transaction(function () use ($request, $capability, $access, $input) {
            $project = $access->resolve($capability);
            $project = Project::whereKey($project->id)->sharedLock()->firstOrFail();
            abort_unless(hash_equals($project->share_digest, hash('sha256', $capability)), 404);
            $module = $access->module($request, $project);
            $folder = empty($input['folder']) ? null : CatalogueEntry::where('revision_id', $module->published_revision_id)
                ->where('kind', 'folder')->findOrFail($input['folder']);
            if ($request->boolean('review')) {
                $photos = DB::table('choices as c')->leftJoin('catalogue_entries as e', function ($join) use ($module) {
                    $join->on('e.file_id', '=', 'c.file_id')->where('e.revision_id', $module->published_revision_id)->where('e.kind', 'photo');
                })->where('c.project_id', $project->id)->select(['c.file_id', 'c.name', 'c.path', 'e.id as entry_id'])
                    ->selectRaw('true as selected')->orderBy('c.id');
            } else {
                $photos = DB::table('catalogue_entries as e')->leftJoin('choices as c', function ($join) use ($project) {
                    $join->on('c.file_id', '=', 'e.file_id')->where('c.project_id', $project->id);
                })->where('e.revision_id', $module->published_revision_id)->where('e.kind', 'photo')
                    ->select(['e.file_id', 'e.name', 'e.path', 'e.id as entry_id'])->selectRaw('(c.id IS NOT NULL) as selected')->orderBy('e.id');
                if ($folder) {
                    $photos->whereRaw('e.file_id IN (WITH RECURSIVE subtree AS (
                        SELECT file_id FROM catalogue_entries WHERE id = ? AND revision_id = ?
                        UNION ALL SELECT c.file_id FROM catalogue_entries c JOIN subtree s ON c.parent_file_id = s.file_id
                        WHERE c.revision_id = ?
                    ) SELECT file_id FROM subtree)', [$folder->id, $module->published_revision_id, $module->published_revision_id]);
                }
            }
            $folders = CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'folder')->whereNotNull('parent_file_id')
                ->select(['id', 'path'])->orderBy('id')->paginate(20, ['*'], 'folder_page');

            return response()->json(['state' => $access->state($request, $project, $module),
                'photos' => $photos->paginate(36), 'folders' => $folders, 'folder' => $folder?->path,
                'legacyFolders' => CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->whereNull('parent_file_id')->exists()]);
        });
    }

    public function mutate(Request $request, string $capability, ManageSelection $selections)
    {
        $data = $request->validate($this->rules() + ['file_id' => 'required|string|max:256', 'selected' => 'required|boolean']);
        $data['selected'] = $request->boolean('selected');

        return response()->json($selections->client($request, $capability, $this->normalize($data)));
    }

    public function confirm(Request $request, string $capability, ManageSelection $selections)
    {
        return response()->json($selections->client($request, $capability, $this->normalize($request->validate($this->rules())), true));
    }

    public function preview(Request $request, string $capability, int $entry, SelectionAccess $access)
    {
        $request->validate(['original' => 'sometimes|boolean']);
        $module = $access->module($request, $access->resolve($capability));
        $photo = CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->findOrFail($entry);
        try {
            $item = DriveItem::fromArray($photo->metadata);
            if ($request->boolean('original')) {
                $stream = app(DriveSource::class)->original($item);
                $type = $item->mimeType;
                $length = $item->size;
            } else {
                $image = app(DrivePreviewSource::class)->thumbnail($item);
                $stream = $image->stream;
                $type = $image->mimeType;
                $length = $image->length;
            }
        } catch (ProviderFailure) {
            abort(502, 'Pratinjau tidak tersedia. Coba lagi atau hubungi fotografer.');
        }

        return response()->stream(function () use ($stream) {
            try {
                while (! $stream->eof()) {
                    echo $stream->read(65536);
                }
            } catch (\Throwable) {
                // A truncated image stays failed; provider/capability details must not be logged.
            } finally {
                $stream->close();
            }
        }, 200, array_filter(['Content-Type' => $type, 'Content-Length' => $length === null ? null : (string) $length,
            'Content-Disposition' => 'inline', 'Cache-Control' => 'no-store, private']));
    }

    private function rules(): array
    {
        return ['operation_id' => 'required|uuid', 'epoch' => 'required|integer|min:1',
            'version' => 'required|integer|min:1', 'revision' => 'required|uuid'];
    }

    private function normalize(array $data): array
    {
        $data['epoch'] = (int) $data['epoch'];
        $data['version'] = (int) $data['version'];
        ksort($data);

        return $data;
    }
}
