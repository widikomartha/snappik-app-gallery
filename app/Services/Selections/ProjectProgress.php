<?php

namespace App\Services\Selections;

use App\Models\AccountAccess;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Models\User;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProjectProgress
{
    public function __construct(private AccountPolicy $accounts) {}

    public function overview(User $actor, bool $dashboard = false): array
    {
        return DB::transaction(function () use ($actor, $dashboard) {
            $actor = $this->lockActor($actor);
            $state = $this->accounts->state($actor);
            abort_unless($actor->role === 'photographer', 403);
            if ($dashboard && $state === 'suspended') {
                return ['accountState' => $state, 'projects' => null, 'checkedAt' => now()->toIso8601String()];
            }
            abort_unless($this->accounts->allows($actor, AccountAction::Read), 403);
            $total = Project::where('user_id', $actor->id)->count();
            $projects = Project::where('user_id', $actor->id)->orderByDesc('created_at')->orderBy('id')
                ->sharedLock()->paginate(20, total: $total)->withPath(url($dashboard ? '/dashboard' : '/projects'))
                ->through(fn ($project) => $this->summary($project, $state));

            return ['accountState' => $state, 'projects' => $projects, 'checkedAt' => now()->toIso8601String()];
        });
    }

    public function read(User $actor, string $id, Closure $callback): mixed
    {
        return DB::transaction(function () use ($actor, $id, $callback) {
            $actor = $this->lockActor($actor);
            // Scope before resolving any project/result data, including admin requests.
            abort_unless($this->accounts->allows($actor, AccountAction::Read), 404);
            $project = Project::where('user_id', $actor->id)->whereKey($id)->sharedLock()->firstOrFail();

            return $callback($project, $this->accounts->state($actor));
        });
    }

    private function lockActor(User $actor): User
    {
        $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
        AccountAccess::whereKey($actor->id)->sharedLock()->first();

        return $actor;
    }

    public function summary(Project $project, string $accountState): array
    {
        $modules = $project->modules()->get()->keyBy('type');
        $module = $modules->get('selection');
        $requested = CatalogueRevision::find($module->requested_revision_id);
        $count = DB::table('choices')->where('project_id', $project->id)->count();
        $context = [];
        if ($accountState === 'expired') {
            $context[] = 'expired';
        }
        if ($project->archived_at) {
            $context[] = 'archived';
        }
        if (! $module->published || ! $module->published_revision_id) {
            $context[] = 'unpublished';
        }
        if ($module->deadline && ! $module->deadline->isFuture()) {
            $context[] = 'deadline';
        }

        return ['id' => $project->id, 'name' => $project->name, 'allowance' => $project->allowance, 'count' => $count,
            'status' => $project->selection_locked_at ? 'confirmed' : ($count ? 'selecting' : 'not_started'),
            'context' => $context, 'updatedAt' => $project->selection_updated_at?->toIso8601String(),
            'epoch' => $project->selection_epoch, 'version' => $project->selection_version,
            'confirmedAt' => $project->selection_locked_at?->toIso8601String(),
            'confirmationCount' => DB::table('selection_confirmations')->where('project_id', $project->id)->count(),
            'publication' => ['selection' => $module->published && $module->published_revision_id !== null,
                'delivery' => $modules->get('delivery')?->published && $modules->get('delivery')?->published_revision_id !== null],
            'catalogueState' => $requested?->state ?? 'empty',
            'revision' => $module->published_revision_id,
            'catalogueStale' => $module->published_revision_id && $requested?->state === 'failed'];
    }

    public function results(Project $project, string $accountState, ?string $confirmation): array
    {
        $snapshot = $confirmation ? DB::table('selection_confirmations')->where('project_id', $project->id)->where('id', $confirmation)->first() : null;
        abort_if($confirmation && ! $snapshot, 404);
        $revision = $project->modules()->where('type', 'selection')->value('published_revision_id');
        $records = $this->choices($project, $confirmation)->select(['id', 'file_id', 'name', 'path', 'revision_id'])
            ->selectSub(fn ($q) => $q->from('catalogue_entries')->selectRaw('COUNT(*)')->where('revision_id', $revision)
                ->where('kind', 'photo')->whereColumn('catalogue_entries.file_id', $confirmation ? 'confirmation_choices.file_id' : 'choices.file_id'), 'available')
            ->orderBy('id')->paginate(20, ['*'], 'choices_page')->withPath(url('/projects/'.$project->id.'/results'))->withQueryString();
        $history = DB::table('selection_confirmations')->where('project_id', $project->id)
            ->select(['id', 'epoch', 'version', 'confirmed_at'])
            ->selectSub(fn ($q) => $q->from('confirmation_choices')->selectRaw('COUNT(*)')
                ->whereColumn('confirmation_id', 'selection_confirmations.id'), 'count')
            ->orderByDesc('epoch')->paginate(10, ['*'], 'history_page')->withPath(url('/projects/'.$project->id.'/results'))->withQueryString();

        return ['progress' => $this->summary($project, $accountState), 'records' => $records, 'history' => $history,
            'snapshot' => $snapshot ? ['id' => $snapshot->id, 'epoch' => $snapshot->epoch, 'confirmedAt' => $snapshot->confirmed_at] : null,
            'checkedAt' => now()->toIso8601String()];
    }

    public function choices(Project $project, ?string $confirmation): Builder
    {
        if ($confirmation) {
            abort_unless(DB::table('selection_confirmations')->where('project_id', $project->id)->where('id', $confirmation)->exists(), 404);

            return DB::table('confirmation_choices')->where('confirmation_id', $confirmation);
        }

        return DB::table('choices')->where('project_id', $project->id);
    }

    /** Full export, bounded server memory. The caller holds the project read lock. */
    public function export(Project $project, ?string $confirmation)
    {
        $stream = fopen('php://temp/maxmemory:1048576', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Filename export temporary storage unavailable.');
        }
        try {
            // Window counts cover the whole set, including names on another UI page.
            $rows = $this->choices($project, $confirmation)->select(['id', 'file_id', 'name', 'path'])
                ->selectRaw('COUNT(*) OVER (PARTITION BY name) AS same_name, COUNT(*) OVER (PARTITION BY path) AS same_path');
            // Wrap before chunking, so window counts are never limited to one chunk.
            DB::query()->fromSub($rows, 'export_choices')->orderBy('id')->chunkById(500, function ($rows) use ($stream) {
                foreach ($rows as $row) {
                    $line = $row->same_name > 1 ? $row->path : $row->name;
                    if ($row->same_name > 1 && $row->same_path > 1) {
                        $line .= ' [ID: '.$row->file_id.']';
                    }
                    // Keep exactly one entry per line, even for unusual original names.
                    $line = str_replace(['\\', "\r", "\n"], ['\\\\', '\\r', '\\n'], $line)."\n";
                    if (fwrite($stream, $line) !== strlen($line)) {
                        throw new \RuntimeException('Filename export temporary storage failed.');
                    }
                }
            });
            rewind($stream);

            return $stream;
        } catch (\Throwable $e) {
            fclose($stream);
            throw $e;
        }
    }
}
