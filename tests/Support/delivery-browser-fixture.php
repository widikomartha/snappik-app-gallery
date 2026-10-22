<?php

use App\Models\AccountAccess;
use App\Models\CatalogueEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DeliveryFixture;
use Tests\Support\DriveFixtures as F;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/\Abrowser-delivery-[a-z0-9-]+@example\.test\z/', $input['email'] ?? '')) {
    exit(2);
}
try {
    if ($input['action'] === 'seed') {
        Queue::fake();
        $builder = new class
        {
            use DeliveryFixture;

            public function create(): Project
            {
                return $this->deliveryProject();
            }
        };
        $project = $builder->create();
        $project->photographer->forceFill(['email' => $input['email'], 'password' => 'BrowserDeliveryPassword123'])->save();
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        $module->update(['password_hash' => Hash::make('Delivery123')]);
        $project->modules()->where('type', 'selection')->update(['password_hash' => Hash::make('Selection123')]);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        foreach (CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->get() as $photo) {
            $metadata = [...F::photo($photo->file_id, $bytes, 'Hasil akhir.png'), 'mimeType' => 'image/png'];
            $photo->update(['name' => 'Hasil akhir.png', 'metadata' => $metadata]);
        }
    } else {
        $owner = User::where('email', $input['email'])->firstOrFail();
        $project = Project::where('user_id', $owner->id)->firstOrFail();
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        if ($input['action'] === 'expire-account') {
            AccountAccess::whereKey($owner->id)->update(['access_ends_at' => now()]);
            $project->modules()->where('type', 'selection')->update(['deadline' => now()]);
        } elseif ($input['action'] === 'revoke-download') {
            foreach (CatalogueEntry::where('revision_id', $module->published_revision_id)->where('kind', 'photo')->get() as $photo) {
                $photo->update(['metadata' => [...$photo->metadata, 'capabilities' => ['canDownload' => false]]]);
            }
        } elseif ($input['action'] === 'close-delivery') {
            $module->update(['deadline' => now()]);
        } else {
            exit(2);
        }
    }
    echo "ok\n";
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).' at '.basename($error->getFile()).':'.$error->getLine()."\n");
    exit(1);
}
