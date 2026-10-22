<?php

namespace App\Http\Controllers\Photographer;

use App\Actions\Projects\ManageProject;
use App\Actions\Selections\ManageSelection;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectConfiguration;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Services\Access\AccountAction;
use App\Services\Access\AccountPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function create(Request $request, AccountPolicy $accounts)
    {
        abort_unless($accounts->allows($request->user(), AccountAction::Mutate), 403);

        return Inertia::render('photographer/project', ['project' => null, 'canMutate' => true]);
    }

    public function store(ProjectConfiguration $request, ManageProject $projects)
    {
        $project = $projects->create($request->user(), $request->validated());

        return to_route('projects.show', $project);
    }

    public function show(Request $request, Project $project, AccountPolicy $accounts)
    {
        Gate::authorize('access', [$project, AccountAction::Read]);
        $modules = $project->modules()->orderByDesc('type')->get()->map(function ($module) use ($project) {
            $revision = CatalogueRevision::find($module->requested_revision_id);
            $published = CatalogueRevision::find($module->published_revision_id);
            $source = $module->source;
            $missing = DB::table('choices')->where('project_id', $project->id)->whereNotExists(function ($query) use ($module) {
                $query->selectRaw('1')->from('catalogue_entries')->where('revision_id', $module->published_revision_id)
                    ->where('kind', 'photo')->whereColumn('catalogue_entries.file_id', 'choices.file_id');
            });

            return ['type' => $module->type, 'published' => $module->published, 'passwordRequired' => $module->password_hash !== null,
                'deadline' => $module->deadline?->toIso8601String(),
                'source' => $source ? 'https://drive.google.com/drive/folders/'.$source['id'].($source['resourceKey'] ? '?resourcekey='.$source['resourceKey'] : '') : '',
                'state' => $revision?->state ?? 'empty', 'failure' => $revision?->failure,
                'stale' => $published && $revision?->state === 'failed',
                'photos' => $published?->entries()->where('kind', 'photo')->count() ?? 0,
                'unsupported' => $revision?->entries()->whereIn('kind', ['unsupported', 'shortcut'])->count() ?? 0,
                'unsupportedNames' => $revision?->entries()->whereIn('kind', ['unsupported', 'shortcut'])->orderBy('id')->limit(20)->pluck('path') ?? [],
                'missingChoices' => $module->type === 'selection' ? (clone $missing)->count() : 0,
                'missingNames' => $module->type === 'selection' ? (clone $missing)->orderBy('id')->limit(20)->pluck('path') : [],
                'canPublish' => $published && $published->state === 'complete' && $published->source === $source];
        });

        return Inertia::render('photographer/project', ['canMutate' => $accounts->allows($request->user(), AccountAction::Mutate),
            'project' => [...$project->only(['id', 'name', 'contact', 'allowance', 'version', 'archived_at', 'selection_epoch', 'selection_version']),
                'selectionLocked' => $project->selection_locked_at !== null, 'modules' => $modules,
                'choiceCount' => DB::table('choices')->where('project_id', $project->id)->count(),
                'choicePreview' => DB::table('choices')->where('project_id', $project->id)->orderBy('id')->limit(20)->get(['file_id', 'name', 'path']),
                'sharePath' => '/g/'.$project->share_token]]);
    }

    public function update(ProjectConfiguration $request, Project $project, ManageProject $projects)
    {
        Gate::authorize('access', [$project, AccountAction::Mutate]);
        $projects->update($request->user(), $project->id, $request->validated());

        return to_route('projects.show', $project);
    }

    public function diagnostics(Request $request, Project $project)
    {
        Gate::authorize('access', [$project, AccountAction::Read]);
        $data = $request->validate(['module' => 'required|in:selection,delivery', 'kind' => 'required|in:unsupported,missing']);
        $module = $project->modules()->where('type', $data['module'])->firstOrFail();
        if ($data['kind'] === 'unsupported') {
            $query = DB::table('catalogue_entries')->where('revision_id', $module->requested_revision_id)->whereIn('kind', ['unsupported', 'shortcut']);
        } else {
            abort_unless($module->type === 'selection', 404);
            $query = DB::table('choices')->where('project_id', $project->id)->whereNotExists(function ($query) use ($module) {
                $query->selectRaw('1')->from('catalogue_entries')->where('revision_id', $module->published_revision_id)
                    ->where('kind', 'photo')->whereColumn('catalogue_entries.file_id', 'choices.file_id');
            });
        }

        return Inertia::render('photographer/project-diagnostics', ['projectId' => $project->id, 'kind' => $data['kind'],
            'records' => $query->select(['id', 'name', 'path'])->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function command(Request $request, Project $project, ManageProject $projects)
    {
        Gate::authorize('access', [$project, AccountAction::Mutate]);
        $data = $request->validate(['version' => 'required|integer|min:1',
            'command' => 'required|in:archive,restore,rotate,refresh,publish,unpublish', 'module' => 'nullable|in:selection,delivery']);
        $projects->command($request->user(), $project->id, $data['version'], $data['command'], $data['module'] ?? null);

        return to_route('projects.show', $project);
    }

    public function reopen(Request $request, Project $project, ManageSelection $selections)
    {
        Gate::authorize('access', [$project, AccountAction::Mutate]);
        $data = $request->validate(['epoch' => 'required|integer|min:1', 'version' => 'required|integer|min:1']);
        $selections->reopen($request->user(), $project->id, ['epoch' => (int) $data['epoch'], 'version' => (int) $data['version']]);

        if ($request->expectsJson()) {
            return response()->json(['reopened' => true]);
        }

        return to_route('projects.show', $project);
    }
}
