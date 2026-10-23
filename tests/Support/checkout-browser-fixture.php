<?php

use App\Actions\Admin\OperatePlatform;
use App\Actions\Billing\ManageVoucher;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\PaymentFixture as P;
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
        // Set a new test-only price version so prior admin suites cannot leak their price here.
        $admin = V::admin();
        app(OperatePlatform::class)->price($admin, (int) DB::table('plans')->where('code', 'pro')->value('current_version_id'), 29000, 'Synthetic checkout fixture price');
        $buyer = F::buyer();
        $buyer->update(['email' => $input['email'], 'password' => 'BrowserCheckoutPassword123']);
        V::create($admin, ['code' => $input['code'], 'per_user_limit' => 1]);
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
    } elseif (in_array($input['action'], ['provider', 'work-payment', 'verify-paid'], true)) {
        $buyer = User::where('email', $input['email'])->firstOrFail();
        $order = Order::where('user_id', $buyer->id)->sole();
        if ($input['action'] === 'provider') {
            if (! in_array($input['state'] ?? null, ['pending', 'settlement', 'expire', 'refund'], true)) {
                exit(2);
            }
            $overrides = ['transaction_status' => $input['state'], 'status_code' => $input['state'] === 'expire' ? '202' : '200'];
            Cache::store('database')->put('synthetic-provider-status-'.$order->provider_order_id, P::body($order, $overrides), 3600);
            echo json_encode(P::signed($order, $overrides), JSON_THROW_ON_ERROR);
            exit(0);
        } elseif ($input['action'] === 'work-payment') {
            $body = Cache::store('database')->get('synthetic-provider-status-'.$order->provider_order_id);
            if (! is_array($body)) {
                exit(2);
            }
            P::gateway(array_fill(0, 10, new Response(200, [], json_encode($body))));
            $app->make(Kernel::class)->call('queue:work', [
                'connection' => 'payments', '--queue' => 'payments', '--stop-when-empty' => true, '--tries' => 1, '--timeout' => 0,
            ]);
        } elseif ($order->state !== 'paid' || ! $buyer->accountAccess->has_paid_access
            || DB::table('entitlement_grants')->where('order_id', $order->id)->count() !== 1
            || DB::table('voucher_reservations')->where('order_id', $order->id)->where('state', 'consumed')->count() !== 1
            || DB::table('voucher_redemptions as d')->join('voucher_reservations as r', 'r.id', '=', 'd.reservation_id')->where('r.order_id', $order->id)->count() !== 1) {
            exit(1);
        }
    } else {
        exit(2);
    }
    echo "ok\n";
} catch (Throwable) {
    exit(1);
}
