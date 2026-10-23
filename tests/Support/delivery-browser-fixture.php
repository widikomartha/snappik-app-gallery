<?php

use App\Models\AccountAccess;
use App\Models\ArchiveJob;
use App\Models\CatalogueEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\Archives\ArchiveRecovery;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DeliveryFixture;
use Tests\Support\DeliveryTransport;
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
        if (str_starts_with($input['email'], 'browser-delivery-product-')) {
            // Each product scenario has a fresh account but shares loopback IP.
            // Reset only its aggregate test-IP counters between scenarios.
            $limiter = new RateLimiter(Cache::store('database'));
            $limiter->clear(md5('gallery-password'.'gallery-ip:127.0.0.1'));
            $limiter->clear(md5('login'.'login-ip:127.0.0.1'));
        }
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
            $photo->update(['name' => 'Hasil akhir.png', 'bytes' => strlen($bytes), 'metadata' => $metadata]);
        }
    } else {
        $owner = User::where('email', $input['email'])->firstOrFail();
        $project = Project::where('user_id', $owner->id)->firstOrFail();
        $module = $project->modules()->where('type', 'delivery')->firstOrFail();
        if ($input['action'] === 'work-archive') {
            DeliveryTransport::bind($app);
            $app->make(Kernel::class)->call('queue:work', [
                'connection' => 'archives', '--queue' => 'archives', '--stop-when-empty' => true, '--tries' => 0, '--timeout' => 0,
            ]);
        } elseif ($input['action'] === 'recover-archive') {
            DeliveryTransport::bind($app);
            ArchiveJob::where('module_id', $module->id)->where('state', 'processing')->update(['lease_until' => now()]);
            $app->make(ArchiveRecovery::class)->sweep();
        } elseif ($input['action'] === 'expire-archive') {
            ArchiveJob::where('module_id', $module->id)->update(['expires_at' => now()]);
        } elseif ($input['action'] === 'cleanup-archives') {
            DeliveryTransport::bind($app);
            ArchiveJob::where('module_id', $module->id)->where('state', 'ready')->update(['expires_at' => now()]);
            $app->make(ArchiveRecovery::class)->sweep();
        } elseif ($input['action'] === 'freeze-account') {
            AccountAccess::whereKey($owner->id)->update(['access_ends_at' => now()]);
        } elseif ($input['action'] === 'expire-account') {
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
