<?php

namespace App\Services\Access;

use App\Models\GalleryModule;
use Illuminate\Http\Request;

class DeliveryAccess
{
    public function __construct(private SelectionAccess $capabilities, private GalleryGate $gate) {}

    public function module(Request $request, #[\SensitiveParameter] string $capability, AccountAction $action = AccountAction::ClientRead): GalleryModule
    {
        $project = $this->capabilities->resolve($capability);
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        $version = $request->session()->get('gallery.'.$module->id);
        abort_unless($this->gate->allows($project->photographer()->firstOrFail(), $action, true,
            $module->published && $module->published_revision_id !== null, $project->archived_at !== null,
            $module->deadline, $module->password_hash !== null, $version !== null, $module->gate_version, $version), 404);

        return $module;
    }
}
