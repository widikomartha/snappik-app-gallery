<?php

use App\Actions\Billing\Checkout;
use App\Actions\Billing\ResumeCheckout;
use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\User;
use App\Services\Billing\GatewayCheckout;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\CheckoutFixture as F;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
F::configure();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$gateway = new class implements PaymentGateway
{
    public int $creates = 0;

    public function create(Order $order): GatewayCheckout
    {
        $this->creates++;
        usleep(200000);

        return new GatewayCheckout('synthetic-race-token', 'https://app.sandbox.midtrans.com/snap/v2/vtweb/synthetic-race-token');
    }

    public function status(Order $order): string
    {
        return 'missing';
    }
};
$app->instance(PaymentGateway::class, $gateway);
try {
    echo 'ready:'.DB::selectOne('select pg_backend_pid() as pid')->pid."\n";
    flush();
    $buyer = User::findOrFail($input['user_id']);
    if ($input['mode'] === 'resume') {
        $app->make(ResumeCheckout::class)->handle($buyer, $input['order_id']);
        echo 'resumed:'.$gateway->creates."\n";
    } else {
        $order = $app->make(Checkout::class)->reserve($buyer, $input['key'], $input['quote']);
        echo 'reserved:'.$order->id."\n";
    }
} catch (ValidationException $e) {
    echo 'rejected:'.array_key_first($e->errors())."\n";
} catch (Throwable $e) {
    echo 'failed:'.get_class($e)."\n";
    exit(1);
}
