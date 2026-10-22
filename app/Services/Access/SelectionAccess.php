<?php

namespace App\Services\Access;

use App\Models\GalleryModule;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectionAccess
{
    public function __construct(private GalleryGate $gate, private AccountPolicy $accounts) {}

    public function resolve(#[\SensitiveParameter] string $capability): Project
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $capability), 404);

        return Project::where('share_digest', hash('sha256', $capability))->firstOrFail();
    }

    public function module(Request $request, Project $project): GalleryModule
    {
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        abort_unless($this->allows($request, $project, $module, AccountAction::ClientRead), 404);

        return $module;
    }

    public function allows(Request $request, Project $project, GalleryModule $module, AccountAction $action): bool
    {
        $sessionVersion = $request->session()->get('gallery.'.$module->id);

        return $this->gate->allows($project->photographer()->firstOrFail(), $action, true,
            $module->published && $module->published_revision_id !== null, $project->archived_at !== null,
            $module->deadline, $module->password_hash !== null, $sessionVersion !== null,
            $module->gate_version, $sessionVersion, $project->selection_locked_at !== null);
    }

    public function state(Request $request, Project $project, GalleryModule $module): array
    {
        return ['epoch' => $project->selection_epoch, 'version' => $project->selection_version,
            'revision' => $module->published_revision_id, 'allowance' => $project->allowance,
            'count' => DB::table('choices')->where('project_id', $project->id)->count(),
            'locked' => $project->selection_locked_at !== null,
            'confirmedAt' => $project->selection_locked_at?->toIso8601String(),
            'accountState' => $this->accounts->state($project->photographer()->firstOrFail()),
            'canWrite' => $this->allows($request, $project, $module, AccountAction::ClientWrite)];
    }
}
