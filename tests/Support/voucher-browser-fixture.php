<?php

use App\Actions\Billing\ManageVoucher;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\VoucherFixture as F;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('/\Abrowser-voucher-[a-z0-9-]+@example\.test\z/', $input['email'] ?? '')) {
    exit(2);
}
try {
    if ($input['action'] === 'seed') {
        $admin = F::admin();
        $admin->update(['email' => $input['email'], 'password' => 'BrowserVoucherPassword123']);
    } else {
        $admin = User::where('email', $input['email'])->where('role', 'admin')->firstOrFail();
        $voucher = Voucher::where('code', $input['code'])->whereHas('versions', fn ($q) => $q->where('created_by', $admin->id))->firstOrFail();
        if ($input['action'] === 'usage') {
            $buyer = User::factory()->create();
            foreach (['held', 'consumed', 'released'] as $state) {
                F::reservation($voucher, $buyer, $state);
            }
        } elseif ($input['action'] === 'edit') {
            $data = $voucher->currentVersion->only(['discount_type', 'discount_value', 'starts_at', 'ends_at', 'total_limit', 'per_user_limit', 'enabled']);
            $app->make(ManageVoucher::class)->save($admin, [...$data, 'expected_version' => $voucher->currentVersion->number, 'discount_value' => 6000, 'reason' => 'Synthetic concurrent owner edit'], $voucher->id);
        } else {
            exit(2);
        }
    }
    echo "ok\n";
} catch (Throwable) {
    exit(1);
}
