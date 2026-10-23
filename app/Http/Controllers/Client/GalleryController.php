<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CatalogueRevision;
use App\Models\Project;
use App\Services\Access\AccountAction;
use App\Services\Access\GalleryGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GalleryController extends Controller
{
    public function show(Request $request, string $capability, GalleryGate $gate)
    {
        $project = $this->resolve($capability);
        $modules = [];
        foreach ($project->modules()->orderByDesc('type')->get() as $module) {
            $version = $request->session()->get('gallery.'.$module->id);
            $base = ['owner' => $project->photographer()->firstOrFail(), 'action' => AccountAction::ClientRead,
                'capabilityValid' => true, 'published' => $module->published && $module->published_revision_id !== null,
                'archived' => $project->archived_at !== null, 'deadline' => $module->deadline,
                'passwordRequired' => $module->password_hash !== null, 'passwordSessionValid' => $version !== null,
                'gateVersion' => $module->gate_version, 'sessionGateVersion' => $version];
            $open = $gate->allows(...$base);
            $available = $gate->allows(...array_replace($base, ['passwordRequired' => false]));
            if ($available) {
                $modules[] = ['type' => $module->type, 'locked' => ! $open,
                    'photos' => $open ? CatalogueRevision::findOrFail($module->published_revision_id)->entries()->where('kind', 'photo')->count() : null];
            }
        }
        abort_if($modules === [], 404);

        return Inertia::render('client/project', ['modules' => $modules]);
    }

    public function unlock(Request $request, string $capability, string $type, GalleryGate $gate)
    {
        $project = $this->resolve($capability);
        $module = $project->modules()->where('type', $type)->firstOrFail();
        abort_unless($gate->allows($project->photographer()->firstOrFail(), AccountAction::ClientRead, true,
            $module->published && $module->published_revision_id !== null, $project->archived_at !== null,
            $module->deadline, false, false, $module->gate_version, null), 404);
        // Media/data requests can replace Laravel's session previous URL. Always return
        // to this project's gate, without relying on a suppressed Referer header.
        $target = route('client.gallery', ['capability' => $capability]);
        $validator = Validator::make($request->all(), ['gallery_password' => 'required|string|max:255']);
        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo($target);
        }
        $data = $validator->validated();
        if (! $module->password_hash || ! Hash::check($data['gallery_password'], $module->password_hash)) {
            throw ValidationException::withMessages(['gallery_password' => 'Kata sandi galeri tidak cocok.'])->redirectTo($target);
        }
        $request->session()->regenerate();
        $request->session()->put('gallery.'.$module->id, $module->gate_version);

        return redirect($target);
    }

    private function resolve(#[\SensitiveParameter] string $capability): Project
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $capability), 404);

        return Project::where('share_digest', hash('sha256', $capability))->firstOrFail();
    }
}
