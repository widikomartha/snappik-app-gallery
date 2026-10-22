<?php

namespace App\Actions\Projects;

use App\Jobs\ImportCatalogue;
use App\Models\AccountAccess;
use App\Models\CatalogueRevision;
use App\Models\GalleryModule;
use App\Models\Project;
use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use App\Services\Drive\DriveReference;
use App\Services\ProviderFailure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ManageProject
{
    public function __construct(private AccountPolicy $accounts) {}

    /** All project mutations and publication use user -> access -> project lock order. */
    public function locked(User $actor, ?string $projectId, callable $operation): mixed
    {
        return DB::transaction(function () use ($actor, $projectId, $operation) {
            $owner = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            AccountAccess::whereKey($owner->id)->lockForUpdate()->first();
            abort_unless($this->accounts->allows($owner, AccountAction::Mutate), 403);
            $project = $projectId === null ? null : Project::where('user_id', $owner->id)->whereKey($projectId)->lockForUpdate()->firstOrFail();

            return $operation($owner, $project);
        });
    }

    public function create(User $actor, array $data): Project
    {
        return $this->locked($actor, null, function (User $owner) use ($data) {
            $this->slot($owner);
            $token = bin2hex(random_bytes(32));
            $project = Project::create(['user_id' => $owner->id, 'name' => $data['name'],
                'contact' => $data['contact'] ?? null, 'allowance' => $data['allowance'],
                'share_token' => $token, 'share_digest' => hash('sha256', $token)]);
            foreach (['selection', 'delivery'] as $type) {
                $project->modules()->create(['type' => $type]);
            }
            $this->configure($project, $data);

            return $project->refresh();
        });
    }

    public function update(User $actor, string $id, array $data): Project
    {
        return $this->locked($actor, $id, function (User $owner, Project $project) use ($data) {
            $this->version($project, $data['version']);
            if ($project->archived_at) {
                $this->invalid('project', 'Pulihkan proyek sebelum mengubah pengaturan.');
            }
            if ($data['allowance'] < DB::table('choices')->where('project_id', $project->id)->count()) {
                $this->invalid('allowance', 'Batas pilihan tidak boleh di bawah jumlah pilihan yang tersimpan.');
            }
            $project->update(['name' => $data['name'], 'contact' => $data['contact'] ?? null,
                'allowance' => $data['allowance'], 'version' => $project->version + 1]);
            $this->configure($project, $data);

            return $project;
        });
    }

    private function configure(Project $project, array $data): void
    {
        $sources = [];
        foreach (['selection', 'delivery'] as $type) {
            try {
                $url = $data[$type.'_source'] ?? '';
                $ref = $url === '' || $url === null ? null : DriveReference::fromFolderUrl($url);
                $sources[$type] = $ref ? ['id' => $ref->id, 'resourceKey' => $ref->resourceKey] : null;
            } catch (ProviderFailure) {
                $this->invalid($type.'_source', 'Gunakan tautan folder Google Drive publik yang valid.');
            }
        }
        if ($sources['selection'] === null) {
            $this->invalid('selection_source', 'Folder sumber pemilihan wajib diisi.');
        }
        if ($sources['selection']['id'] === ($sources['delivery']['id'] ?? null)) {
            $this->invalid('delivery_source', 'Gunakan folder hasil akhir yang terpisah dari pemilihan.');
        }
        foreach (['selection', 'delivery'] as $type) {
            $module = $project->modules()->where('type', $type)->lockForUpdate()->firstOrFail();
            $changed = $module->source !== $sources[$type];
            if ($changed && $type === 'selection' && $project->selection_locked_at) {
                $this->invalid('selection_source', 'Buka kembali konfirmasi sebelum mengganti sumber pemilihan.');
            }
            $password = $data[$type.'_password'] ?? null;
            $remove = $data[$type.'_remove_password'] ?? false;
            $deadline = $data[$type.'_deadline'] ?? null;
            $gateChanged = $password !== null || $remove || $module->deadline?->toIso8601String() !== ($deadline ? CarbonImmutable::parse($deadline)->toIso8601String() : null) || $changed;
            $module->forceFill(['source' => $sources[$type], 'deadline' => $deadline,
                'password_hash' => $remove ? null : ($password !== null ? Hash::make($password) : $module->password_hash),
                'gate_version' => $module->gate_version + (int) $gateChanged])->save();
            if ($changed) {
                if ($sources[$type] === null) {
                    $module->update(['published' => false, 'requested_revision_id' => null, 'published_revision_id' => null]);
                } else {
                    $this->queue($module);
                }
            }
        }
    }

    public function command(User $actor, string $id, int $version, string $command, ?string $type = null): void
    {
        $this->locked($actor, $id, function (User $owner, Project $project) use ($version, $command, $type) {
            $this->version($project, $version);
            if ($command === 'archive' || $command === 'restore') {
                if ($command === 'restore' && $project->archived_at) {
                    $this->slot($owner);
                }
                $project->update(['archived_at' => $command === 'archive' ? now() : null]);
                $project->modules()->increment('gate_version');
            } elseif ($command === 'rotate') {
                $token = bin2hex(random_bytes(32));
                $project->update(['share_token' => $token, 'share_digest' => hash('sha256', $token)]);
                $project->modules()->increment('gate_version');
            } else {
                if ($project->archived_at) {
                    $this->invalid('project', 'Pulihkan proyek sebelum mengelola galeri.');
                }
                $module = $project->modules()->where('type', $type)->lockForUpdate()->firstOrFail();
                if ($command === 'refresh') {
                    if ($module->source === null) {
                        $this->invalid('project', 'Atur folder sumber terlebih dahulu.');
                    }
                    $this->queue($module);
                } elseif ($command === 'publish') {
                    $revision = CatalogueRevision::find($module->published_revision_id);
                    if (! $revision || $revision->state !== 'complete' || $revision->source !== $module->source) {
                        $this->invalid('project', 'Tunggu impor sumber saat ini selesai sebelum menerbitkan galeri.');
                    }
                    $module->update(['published' => true, 'gate_version' => $module->gate_version + 1]);
                } elseif ($command === 'unpublish') {
                    $module->update(['published' => false, 'gate_version' => $module->gate_version + 1]);
                } else {
                    abort(404);
                }
            }
            $project->increment('version');
        });
    }

    private function queue(GalleryModule $module): void
    {
        $revision = CatalogueRevision::create(['module_id' => $module->id, 'source' => $module->source]);
        $module->update(['requested_revision_id' => $revision->id]);
        // The database queue row and request pointer commit together; no lost dispatch window.
        ImportCatalogue::dispatch($revision->id)->onConnection('catalogues')->onQueue('catalogues');
    }

    private function slot(User $owner): void
    {
        if ($this->accounts->state($owner) === 'trial' && Project::where('user_id', $owner->id)->whereNull('archived_at')->count() >= 3) {
            $this->invalid('project', 'Uji coba mencakup maksimal 3 proyek aktif, termasuk draf. Arsipkan proyek terlebih dahulu.');
        }
    }

    private function version(Project $project, int $version): void
    {
        if ($project->version !== $version) {
            $this->invalid('project', 'Pengaturan telah berubah. Muat ulang sebelum menyimpan kembali.');
        }
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
