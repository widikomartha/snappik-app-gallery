<?php

use App\Actions\Selections\ManageSelection;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Test-only process entry point. Capability/payload arrives on stdin and is never printed.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    exit(2);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    $request = Request::create('/', 'POST');
    $request->setLaravelSession($app->make('session')->driver());
    echo "ready\n";
    flush();
    $app->make(ManageSelection::class)->client($request, $input['capability'], $input['data'], $input['confirm']);
    echo "200\n";
} catch (HttpResponseException $error) {
    echo $error->getResponse()->getStatusCode()."\n";
} catch (HttpException $error) {
    echo $error->getStatusCode()."\n";
} catch (Throwable) {
    echo "failed\n";
    exit(1);
}
