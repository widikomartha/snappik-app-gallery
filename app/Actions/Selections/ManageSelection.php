<?php

namespace App\Actions\Selections;

use App\Actions\Projects\ManageProject;
use App\Models\AccountAccess;
use App\Models\CatalogueEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Access\SelectionAccess;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageSelection
{
    public function __construct(private SelectionAccess $access) {}

    public function client(Request $request, #[\SensitiveParameter] string $capability, array $data, bool $confirm = false): array
    {
        $resolved = $this->access->resolve($capability);

        return DB::transaction(function () use ($request, $capability, $resolved, $data, $confirm) {
            // Same lock order as configuration, entitlement changes and catalogue publication.
            User::whereKey($resolved->user_id)->lockForUpdate()->firstOrFail();
            AccountAccess::whereKey($resolved->user_id)->lockForUpdate()->first();
            $project = Project::whereKey($resolved->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($project->share_digest, hash('sha256', $capability)), 404);
            $module = $this->access->module($request, $project);
            $state = fn () => $this->access->state($request, $project, $module);
            $fail = function (int $status, string $message) use ($state): never {
                throw new HttpResponseException(response()->json(['message' => $message, 'state' => $state()], $status));
            };
            $digest = hash('sha256', json_encode([$confirm ? 'confirm' : 'choice', $data], JSON_THROW_ON_ERROR));
            $receipt = DB::table('mutation_receipts')->where('project_id', $project->id)->where('operation_id', $data['operation_id'])->first();
            if ($receipt) {
                if (! hash_equals($receipt->payload_digest, $digest)) {
                    $fail(409, 'ID operasi sudah digunakan untuk perubahan lain. Muat ulang pilihan.');
                }

                return ['state' => $state(), 'result' => json_decode($receipt->result, true), 'replayed' => true];
            }
            // Repeating the same reviewed confirmation with a fresh request ID also
            // returns the existing immutable result, while a reopened epoch cannot.
            if ($confirm && $project->selection_locked_at && $project->selection_epoch === $data['epoch']
                && $module->published_revision_id === $data['revision']) {
                $existing = DB::table('selection_confirmations')->where('project_id', $project->id)
                    ->where('epoch', $data['epoch'])->where('version', $data['version'])->first();
                if ($existing) {
                    $result = ['confirmationId' => $existing->id, 'version' => $existing->version + 1];
                    DB::table('mutation_receipts')->insert(['project_id' => $project->id, 'operation_id' => $data['operation_id'],
                        'payload_digest' => $digest, 'result' => json_encode($result, JSON_THROW_ON_ERROR)]);

                    return ['state' => $state(), 'result' => $result, 'replayed' => true];
                }
            }
            if (! $this->access->allows($request, $project, $module, AccountAction::ClientWrite)) {
                $fail(403, $project->selection_locked_at ? 'Pilihan sudah dikonfirmasi dan dikunci.' : 'Masa akses fotografer berakhir. Pilihan tersimpan tetap tersedia.');
            }
            if ($project->selection_epoch !== $data['epoch'] || $project->selection_version !== $data['version'] || $module->published_revision_id !== $data['revision']) {
                $fail(409, 'Pilihan atau katalog berubah di sesi lain. Muat ulang dan tinjau kembali.');
            }
            $choices = DB::table('choices')->where('project_id', $project->id);
            if ($confirm) {
                if ($choices->count() < 1 || $choices->count() > $project->allowance) {
                    $fail(422, 'Pilih minimal satu foto, hingga batas pilihan, sebelum konfirmasi.');
                }
                $missing = (clone $choices)->whereNotExists(fn ($query) => $query->selectRaw('1')->from('catalogue_entries')
                    ->where('revision_id', $module->published_revision_id)->where('kind', 'photo')->whereColumn('catalogue_entries.file_id', 'choices.file_id'))->exists();
                if ($missing) {
                    $fail(422, 'Ada foto pilihan yang tidak tersedia. Hapus pilihan tersebut sebelum konfirmasi.');
                }
                $id = (string) Str::uuid();
                DB::table('selection_confirmations')->insert(['id' => $id, 'project_id' => $project->id,
                    'epoch' => $project->selection_epoch, 'version' => $project->selection_version, 'confirmed_at' => now()]);
                DB::table('confirmation_choices')->insertUsing(['confirmation_id', 'file_id', 'name', 'path', 'revision_id'],
                    (clone $choices)->selectRaw('? as confirmation_id, file_id, name, path, revision_id', [$id]));
                $project->selection_locked_at = now();
                $project->version++;
                $result = ['confirmationId' => $id];
            } else {
                $choice = (clone $choices)->where('file_id', $data['file_id']);
                if ($data['selected']) {
                    $entry = CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->where('file_id', $data['file_id'])->first();
                    abort_unless($entry, 404);
                    if (! $choice->exists()) {
                        if ($choices->count() >= $project->allowance) {
                            $fail(422, 'Batas pilihan tercapai. Hapus salah satu pilihan terlebih dahulu.');
                        }
                        DB::table('choices')->insert(['project_id' => $project->id, 'file_id' => $entry->file_id,
                            'name' => $entry->name, 'path' => $entry->path, 'revision_id' => $entry->revision_id,
                            'created_at' => now(), 'updated_at' => now()]);
                    }
                } else {
                    // Unavailable choices remain visible in review and can be explicitly removed.
                    abort_unless($choice->exists() || CatalogueEntry::where('revision_id', $module->published_revision_id)
                        ->where('kind', 'photo')->where('file_id', $data['file_id'])->exists(), 404);
                    $choice->delete();
                }
                $result = ['fileId' => $data['file_id'], 'selected' => $data['selected']];
            }
            $project->selection_version++;
            $project->selection_updated_at = now();
            $project->save();
            $result['version'] = $project->selection_version;
            DB::table('mutation_receipts')->insert(['project_id' => $project->id, 'operation_id' => $data['operation_id'],
                'payload_digest' => $digest, 'result' => json_encode($result, JSON_THROW_ON_ERROR)]);

            return ['state' => $state(), 'result' => $result, 'replayed' => false];
        });
    }

    public function reopen(User $owner, string $id, array $data): void
    {
        app(ManageProject::class)->locked($owner, $id, function (User $owner, Project $project) use ($data) {
            $module = $project->modules()->where('type', 'selection')->firstOrFail();
            abort_if($project->archived_at || ($module->deadline && ! $module->deadline->isFuture()), 403);
            abort_unless($project->selection_epoch === $data['epoch'] && $project->selection_version === $data['version'], 409);
            abort_unless($project->selection_locked_at, 409);
            $project->update(['selection_locked_at' => null, 'selection_epoch' => $project->selection_epoch + 1,
                'selection_version' => $project->selection_version + 1, 'version' => $project->version + 1, 'selection_updated_at' => now()]);
        });
    }
}
