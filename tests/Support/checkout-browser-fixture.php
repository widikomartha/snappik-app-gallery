<?php

use App\Actions\Billing\ManageVoucher;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\VoucherFixture as V;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/\Abrowser-checkout-[a-z0-9-]+@example\.test\z/', $input['email'] ?? '')) {
    exit(2);
}
try {
    F::configure();
    if ($input['action'] === 'seed') {
        $buyer = F::buyer();
        $buyer->update(['email' => $input['email'], 'password' => 'BrowserCheckoutPassword123']);
        V::create(V::admin(), ['code' => $input['code'], 'per_user_limit' => 1]);
    } elseif ($input['action'] === 'deactivate') {
        $voucher = Voucher::where('code', $input['code'])->firstOrFail();
        $admin = User::findOrFail($voucher->currentVersion->created_by);
        app(ManageVoucher::class)->save($admin, [...V::terms(), 'enabled' => false, 'expected_version' => 1], $voucher->id);
    } elseif ($input['action'] === 'verify') {
        $buyer = User::where('email', $input['email'])->firstOrFail();
        if (DB::table('orders')->where('user_id', $buyer->id)->count() !== 1
            || DB::table('voucher_reservations')->where('user_id', $buyer->id)->where('state', 'held')->count() !== 1
            || $buyer->accountAccess->has_paid_access) {
            exit(1);
        }
    } else {
        exit(2);
    }
    echo "ok\n";
} catch (Throwable) {
    exit(1);
}
