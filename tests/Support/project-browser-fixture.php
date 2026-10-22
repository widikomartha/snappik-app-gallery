<?php

use App\Actions\Auth\VerifyAccount;
use App\Contracts\DriveSource;
use App\Models\AccountAccess;
use App\Models\CatalogueEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogueDrive;
use Tests\Support\DriveFixtures as F;

// CLI-only test fixture. Never registered as an HTTP route or production command.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    if ($input['action'] === 'seed') {
        if (! preg_match('/\Abrowser-project-[a-z0-9-]+@example\.test\z/', $input['email'])) {
            exit(2);
        }
        $user = User::factory()->unverified()->create(['email' => $input['email'], 'password' => 'BrowserProjectPassword123']);
        $app->make(VerifyAccount::class)->handle($user);
    } elseif ($input['action'] === 'work') {
        $drive = new CatalogueDrive;
        $drive->pages = ['browser-selection:' => [[F::folder('browser-nested')]],
            'browser-nested:' => [[F::photo('browser-one'), ['id' => 'browser-raw', 'name' => 'camera.raw', 'mimeType' => 'image/x-raw']], 'next'],
            'browser-nested:next' => [[F::photo('browser-two')]],
            'browser-delivery:' => [[F::photo('browser-final')]]];
        $app->instance(DriveSource::class, $drive);
        // Real database queue deserialization, dependency injection and job deletion.
        $app->make(Kernel::class)->call('queue:work', ['connection' => 'catalogues', '--queue' => 'catalogues',
            '--stop-when-empty' => true, '--timeout' => 0, '--tries' => 0]);
    } elseif (in_array($input['action'], ['grow-selection', 'expire-selection-account', 'close-selection-deadline'], true)) {
        if (! preg_match('/\Abrowser-project-selection-[a-z0-9-]+@example\.test\z/', $input['email'])) {
            exit(2);
        }
        $owner = User::where('email', $input['email'])->firstOrFail();
        $project = Project::where('user_id', $owner->id)->firstOrFail();
        $module = $project->modules()->where('type', 'selection')->firstOrFail();
        if ($input['action'] === 'expire-selection-account') {
            AccountAccess::whereKey($owner->id)->update(['access_ends_at' => now()]);
        } elseif ($input['action'] === 'close-selection-deadline') {
            $module->update(['deadline' => now()]);
        } else {
            $photo = CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->firstOrFail();
            $attributes = $photo->getAttributes();
            unset($attributes['id']);
            DB::transaction(function () use ($attributes) {
                $rows = [];
                for ($i = 0; $i < 1999; $i++) {
                    $rows[] = [...$attributes, 'file_id' => 'browser-page-'.$i];
                    if (count($rows) === 200) {
                        DB::table('catalogue_entries')->insert($rows);
                        $rows = [];
                    }
                }
                if ($rows !== []) {
                    DB::table('catalogue_entries')->insert($rows);
                }
            });
        }
    } else {
        exit(2);
    }
    echo "ok\n";
} catch (Throwable) {
    // No exception, email, password or capability is emitted.
    exit(1);
}
