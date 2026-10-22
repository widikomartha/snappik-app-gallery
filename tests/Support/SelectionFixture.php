<?php

namespace Tests\Support;

use App\Actions\Auth\VerifyAccount;
use App\Actions\Projects\ManageProject;
use App\Contracts\DriveSource;
use App\Models\Project;
use App\Models\User;
use App\Services\Drive\ImportProjectCatalogue;
use Illuminate\Support\Str;

trait SelectionFixture
{
    private function selectionProject(int $allowance = 2): Project
    {
        $owner = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($owner);
        $project = app(ManageProject::class)->create($owner->fresh(), ['name' => 'Synthetic selection', 'allowance' => $allowance,
            'selection_source' => 'https://drive.google.com/drive/folders/selection-root']);
        $drive = new CatalogueDrive;
        $drive->pages = ['selection-root:' => [[DriveFixtures::folder('nested')]],
            'nested:' => [[DriveFixtures::photo('one'), DriveFixtures::photo('two')]]];
        app()->instance(DriveSource::class, $drive);
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        app(ImportProjectCatalogue::class)->handle($module->requested_revision_id);
        app(ManageProject::class)->command($owner->fresh(), $project->id, $project->fresh()->version, 'publish', 'selection');

        return $project->fresh();
    }

    private function selectionPayload(Project $project, string $file = 'one', bool $selected = true): array
    {
        $project->refresh();

        return ['operation_id' => (string) Str::uuid(), 'epoch' => $project->selection_epoch,
            'version' => $project->selection_version,
            'revision' => $project->modules()->where('type', 'selection')->value('published_revision_id'),
            'file_id' => $file, 'selected' => $selected];
    }
}
