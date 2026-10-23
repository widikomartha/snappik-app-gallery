<?php

use App\Actions\Admin\OperatePlatform;
use App\Actions\Auth\SetAccountSuspension;
use App\Contracts\ArchiveStorage;
use App\Contracts\DeliveryArchiveStorage;
use App\Contracts\DriveSource;
use App\Contracts\PaymentGateway;
use App\Integrations\Midtrans\SandboxGateway;
use App\Models\ArchiveJob;
use App\Models\CatalogueRevision;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\Archives\DeliveryArchives;
use App\Services\Drive\DriveItem;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\StreamInterface;
use Tests\Support\ArchiveDisk;
use Tests\Support\CatalogueDrive;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\DeliveryFixture;
use Tests\Support\DriveFixtures as D;
use Tests\Support\PaymentFixture as P;
use Tests\Support\VoucherFixture as V;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'snappik_test') {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/\Abrowser-operations-[a-z0-9-]+@example\.test\z/', $input['email'] ?? '')) {
    exit(2);
}
try {
    F::configure();
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
        $project->update(['name' => $input['name']]);
        $owner = $project->photographer;
        $owner->update(['email' => str_replace('browser-operations-', 'buyer-operations-', $input['email'])]);
        $admin = V::admin();
        $admin->update(['email' => $input['email'], 'password' => 'BrowserOperationsPassword123']);
        $revision = CatalogueRevision::find($project->modules()->where('type', 'selection')->value('requested_revision_id'));
        $revision->update(['state' => 'failed', 'failure' => 'catalogue_worker_failed']);
        $archive = DB::transaction(fn () => app(DeliveryArchives::class)->request($project->modules()->where('type', 'delivery')->first()));
        $archive->update(['state' => 'failed', 'failure' => 'archive_worker_interrupted']);
        $order = F::reserve($owner);
        $order->update(['state' => 'review', 'review_reason' => 'midtrans_amount_mismatch']);
        echo json_encode(['owner' => $owner->id, 'order' => $order->id, 'import' => $revision->id, 'archive' => $archive->id]);
    } else {
        $owner = User::where('email', str_replace('browser-operations-', 'buyer-operations-', $input['email']))->firstOrFail();
        $project = Project::where('user_id', $owner->id)->firstOrFail();
        if ($input['action'] === 'work-import') {
            $drive = new CatalogueDrive;
            $drive->pages = ['selection-root:' => [[D::photo('selection-one')]]];
            $app->instance(DriveSource::class, $drive);
            $app->make(Kernel::class)->call('queue:work', ['connection' => 'catalogues', '--queue' => 'catalogues', '--stop-when-empty' => true, '--timeout' => 0, '--tries' => 1]);
            $revision = CatalogueRevision::find($project->modules()->where('type', 'selection')->value('requested_revision_id'));
            if ($revision->state !== 'complete') {
                exit(3);
            }
        } elseif ($input['action'] === 'work-archive') {
            $drive = new class extends CatalogueDrive
            {
                public function original(DriveItem $expected): StreamInterface
                {
                    return Utils::streamFor('original-one');
                }
            };
            $app->instance(DriveSource::class, $drive);
            $disk = new ArchiveDisk;
            $app->instance(ArchiveStorage::class, $disk);
            $app->instance(DeliveryArchiveStorage::class, $disk);
            $app->make(Kernel::class)->call('queue:work', ['connection' => 'archives', '--queue' => 'archives', '--stop-when-empty' => true, '--timeout' => 0, '--tries' => 1]);
            $job = ArchiveJob::where('module_id', $project->modules()->where('type', 'delivery')->value('id'))->firstOrFail();
            if ($job->state !== 'ready') {
                exit(3);
            }
            $disk->cleanup($job->object_key);
        } elseif ($input['action'] === 'work-payment') {
            $order = Order::where('user_id', $owner->id)->firstOrFail();
            $handler = new MockHandler;
            $reply = function ($request) use (&$reply, $handler) {
                $handler->append($reply);
                $providerId = basename(dirname($request->getUri()->getPath()));

                return P::response(Order::where('provider_order_id', $providerId)->firstOrFail(), ['transaction_status' => 'pending', 'status_code' => '201']);
            };
            $handler->append($reply);
            $app->instance(PaymentGateway::class, new SandboxGateway(
                new Client(['handler' => HandlerStack::create($handler)]), 'synthetic-test-secret', 'synthetic-merchant'));
            $app->make(Kernel::class)->call('queue:work', ['connection' => 'payments', '--queue' => 'payments', '--stop-when-empty' => true, '--timeout' => 60, '--tries' => 1]);
            if (! $order->paymentEvents()->where('state', 'processed')->exists()) {
                exit(3);
            }
        } elseif ($input['action'] === 'price-race') {
            $admin = User::where('email', $input['email'])->firstOrFail();
            app(OperatePlatform::class)->price($admin, (int) DB::table('plans')->where('code', 'pro')->value('current_version_id'), 39000, 'Synthetic concurrent price change');
        } elseif ($input['action'] === 'suspend-race') {
            $admin = User::where('email', $input['email'])->firstOrFail();
            app(SetAccountSuspension::class)->handle($admin, $owner, true, 'Synthetic concurrent suspension');
        } else {
            exit(2);
        }
        echo '{}';
    }
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).' at '.basename($error->getFile()).':'.$error->getLine()."\n");
    exit(1);
}
