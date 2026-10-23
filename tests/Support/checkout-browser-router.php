<?php

// CLI-only synthetic transport behind the real sandbox adapter. No provider network.
use App\Contracts\PaymentGateway;
use App\Integrations\Midtrans\SandboxGateway;
use App\Models\Order;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CheckoutFixture as F;

if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'snappik_test') {
    http_response_code(404);
    exit;
}
$public = realpath(__DIR__.'/../../public');
$file = realpath($public.urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->booted(function () use ($app) {
    if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'snappik_test') {
        abort(404);
    }
    F::configure();
    $http = new Client(['handler' => function ($request) {
        if ($request->getMethod() === 'GET') {
            $id = rawurldecode(explode('/', $request->getUri()->getPath())[2] ?? '');
            $body = Cache::get('synthetic-provider-status-'.$id);
            if ($body !== null && Order::where('provider_order_id', $id)->exists()) {
                return Create::promiseFor(new Response(200, [], json_encode($body)));
            }

            return Create::promiseFor(new Response(404, [], '{"status_code":"404"}'));
        }
        $body = json_decode((string) $request->getBody(), true);
        $id = $body['transaction_details']['order_id'];
        if (Cache::add('synthetic-checkout-create-'.$id, true, 3600)) {
            throw new ConnectException('Synthetic creation timeout', $request);
        }

        return Create::promiseFor(new Response(201, [], json_encode(['token' => 'synthetic-browser-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/synthetic-browser-token'])));
    }]);
    $app->instance(PaymentGateway::class, new SandboxGateway($http, 'synthetic-test-secret', 'synthetic-merchant'));
});
$app->handleRequest(Request::capture());
