<?php

use App\Contracts\DriveSource;
use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Services\Billing\GatewayCheckout;
use App\Services\Billing\GatewayStatus;
use App\Services\Billing\PaymentProcessing;
use App\Services\Drive\DriveItem;
use App\Services\Drive\DrivePage;
use App\Services\Drive\DriveReference;
use App\Services\Drive\ImportProjectCatalogue;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogueDrive;
use Tests\Support\DriveFixtures;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    if ($input['action'] === 'import') {
        app()->instance(DriveSource::class, new class($input['hang']) extends CatalogueDrive
        {
            public function __construct(private bool $hang) {}

            public function children(DriveReference $folder, ?string $pageToken = null): DrivePage
            {
                $start = (int) ($pageToken ?? 0);
                if ($this->hang && $start === 250) {
                    echo "blocked\n";
                    flush();
                    while (true) {
                        usleep(100000);
                    }
                }
                $items = [];
                for ($i = $start; $i < min(3000, $start + 250); $i++) {
                    $items[] = DriveItem::fromArray(DriveFixtures::photo('generated-'.$i));
                }

                return new DrivePage($items, $start + 250 < 3000 ? (string) ($start + 250) : null);
            }
        });
        app(ImportProjectCatalogue::class)->handle($input['id']);
        echo json_encode(['peak_php_bytes' => memory_get_peak_usage(true)])."\n";
    } else {
        app()->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public function create(Order $order): GatewayCheckout
            {
                throw new LogicException('No checkout creation in recovery proof.');
            }

            public function status(Order $order): GatewayStatus
            {
                return new GatewayStatus('settlement', 'synthetic-'.$order->id, now()->toImmutable());
            }
        });
        if ($input['hang']) {
            DB::listen(function ($query) {
                if (str_starts_with($query->sql, 'insert into "entitlement_grants"')) {
                    // Kill after the grant INSERT, before the financial transaction commits.
                    echo "blocked\n";
                    flush();
                    while (true) {
                        usleep(100000);
                    }
                }
            });
        }
        app(PaymentProcessing::class)->process($input['id']);
        echo "processed\n";
    }
} catch (Throwable) {
    fwrite(STDERR, "Synthetic recovery worker failed.\n");
    exit(1);
}
