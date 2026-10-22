<?php

use App\Contracts\ArchiveStorage;
use App\Contracts\DeliveryArchiveStorage;
use App\Contracts\DriveSource;
use App\Jobs\BuildDeliveryArchive;
use App\Services\Archives\DeliveryArchives;
use App\Services\Drive\DriveItem;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Psr\Http\Message\StreamInterface;
use Tests\Support\ArchiveDisk;
use Tests\Support\CatalogueDrive;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    if ($input['action'] === 'request') {
        $request = Request::create('/synthetic', 'POST');
        $request->setLaravelSession(app('session')->driver());
        echo "ready\n";
        flush();
        $archives = app(DeliveryArchives::class);
        $id = $archives->authorized($request, $input['capability'], fn ($module) => $archives->request($module)->id);
        echo $id."\n";
    } else {
        $disk = new ArchiveDisk;
        app()->instance(ArchiveStorage::class, $disk);
        app()->instance(DeliveryArchiveStorage::class, $disk);
        app()->instance(DriveSource::class, new class($input['action'] === 'hang') extends CatalogueDrive
        {
            public function __construct(private bool $hang) {}

            public function original(DriveItem $expected): StreamInterface
            {
                if ($this->hang) {
                    echo "streaming\n";
                    flush();
                    while (true) {
                        usleep(100000);
                    }
                }

                return Utils::streamFor('original-one');
            }
        });
        (new BuildDeliveryArchive($input['archive_id']))->handle(app(DeliveryArchives::class));
        echo "done\n";
    }
} catch (Throwable) {
    fwrite(STDERR, "Synthetic archive process failed.\n");
    exit(1);
}
