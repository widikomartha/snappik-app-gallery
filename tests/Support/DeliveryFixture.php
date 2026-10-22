<?php

namespace Tests\Support;

use App\Actions\Auth\VerifyAccount;
use App\Actions\Projects\ManageProject;
use App\Contracts\DriveSource;
use App\Models\Project;
use App\Models\User;
use App\Services\Drive\ImportProjectCatalogue;

trait DeliveryFixture
{
    private function deliveryProject(): Project
    {
        $owner = User::factory()->unverified()->create();
        app(VerifyAccount::class)->handle($owner);
        $project = app(ManageProject::class)->create($owner->fresh(), ['name' => 'Synthetic finished gallery', 'allowance' => 2,
            'selection_source' => 'https://drive.google.com/drive/folders/selection-root',
            'delivery_source' => 'https://drive.google.com/drive/folders/delivery-root']);
        $drive = new CatalogueDrive;
        $drive->pages = ['selection-root:' => [[DriveFixtures::photo('selection-one')]],
            'delivery-root:' => [[DriveFixtures::folder('delivery-nested')]],
            'delivery-nested:' => [[DriveFixtures::photo('finished-one'), DriveFixtures::photo('finished-two')]]];
        app()->instance(DriveSource::class, $drive);
        foreach (['selection', 'delivery'] as $type) {
            $module = $project->modules()->where('type', $type)->firstOrFail();
            app(ImportProjectCatalogue::class)->handle($module->requested_revision_id);
            app(ManageProject::class)->command($owner->fresh(), $project->id, $project->fresh()->version, 'publish', $type);
        }

        return $project->fresh();
    }
}
