<?php

use App\Actions\Billing\ApplyPayment;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CheckoutFixture as F;
use Tests\Support\PaymentFixture as P;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
F::configure();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    $event = PaymentEvent::findOrFail($input['event_id']);
    $order = Order::findOrFail($event->order_id);
    $gateway = P::gateway([P::response($order, ['transaction_status' => $input['state'], 'status_code' => $input['state'] === 'expire' ? '202' : '200'])]);
    $status = $gateway->status($order);
    echo 'ready:'.DB::selectOne('select pg_backend_pid() as pid')->pid."\n";
    flush();
    $app->make(ApplyPayment::class)->handle($event->id, $status);
    echo "applied\n";
} catch (Throwable) {
    echo "failed:payment_race\n";
    exit(1);
}
