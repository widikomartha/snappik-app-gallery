<?php

// Dedicated loopback test front controller; never included by the application.
use Illuminate\Http\Request;
use Tests\Support\DeliveryTransport;

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
    DeliveryTransport::bind($app);
});
$app->handleRequest(Request::capture());
