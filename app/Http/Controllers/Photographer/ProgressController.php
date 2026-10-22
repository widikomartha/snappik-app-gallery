<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Selections\ProjectProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgressController extends Controller
{
    public function index(Request $request, ProjectProgress $progress)
    {
        $dashboard = $request->routeIs('dashboard', 'dashboard.data');
        $data = $progress->overview($request->user(), $dashboard);

        return $request->routeIs('*.data') ? response()->json($data) : Inertia::render($dashboard ? 'photographer/dashboard' : 'photographer/projects', $data);
    }

    public function show(Request $request, Project $project, ProjectProgress $progress)
    {
        $data = $request->validate(['confirmation' => 'nullable|uuid', 'choices_page' => 'sometimes|integer|min:1', 'history_page' => 'sometimes|integer|min:1']);
        $result = $progress->read($request->user(), $project->id,
            fn ($project, $state) => $progress->results($project, $state, $data['confirmation'] ?? null));

        return $request->routeIs('*.data') ? response()->json($result) : Inertia::render('photographer/results', $result);
    }

    public function export(Request $request, Project $project, ProjectProgress $progress)
    {
        $data = $request->validate(['confirmation' => 'nullable|uuid', 'version' => 'sometimes|integer|min:1', 'revision' => 'nullable|uuid']);
        $stream = $progress->read($request->user(), $project->id,
            function ($project) use ($progress, $data) {
                if (empty($data['confirmation']) && isset($data['version'])) {
                    abort_unless($project->selection_version === (int) $data['version']
                        && $project->modules()->where('type', 'selection')->value('published_revision_id') === ($data['revision'] ?? null), 409);
                }

                return $progress->export($project, $data['confirmation'] ?? null);
            });

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="selected-filenames.txt"']);
    }
}
