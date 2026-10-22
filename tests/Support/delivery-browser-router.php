<?php

// Dedicated loopback test front controller; never included by the application.
use App\Contracts\DrivePreviewSource;
use App\Contracts\DriveRangeSource;
use App\Contracts\DriveSource;
use App\Integrations\GoogleDrive\PublicDriveSource;
use App\Models\CatalogueEntry;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Tests\Support\DriveFixtures as F;

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
    $handler = new MockHandler;
    // Every upstream request terminates in this local handler, including thumbnails.
    $reply = function ($request) use (&$reply, $handler) {
        $handler->append($reply);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        if ($request->getUri()->getHost() === 'lh3.googleusercontent.com') {
            return new Response(200, ['Content-Type' => 'image/png', 'Content-Length' => (string) strlen($bytes)], $bytes);
        }
        $id = basename($request->getUri()->getPath());
        $entry = CatalogueEntry::where('file_id', $id)->where('kind', 'photo')->latest('id')->firstOrFail();
        if (! str_starts_with($request->getUri()->getPath(), '/download/')) {
            return F::json([...$entry->metadata, 'thumbnailLink' => 'https://lh3.googleusercontent.com/synthetic-image']);
        }
        if (preg_match('/\Abytes=(\d+)-(\d+)\z/', $request->getHeaderLine('Range'), $range)) {
            return new Response(206, ['Content-Type' => 'image/png', 'Content-Range' => "bytes {$range[1]}-{$range[2]}/".strlen($bytes)], substr($bytes, (int) $range[1], (int) $range[2] - (int) $range[1] + 1));
        }

        return new Response(200, ['Content-Type' => 'image/png', 'Content-Length' => (string) strlen($bytes)], $bytes);
    };
    $handler->append($reply);
    $source = new PublicDriveSource(new Client(['handler' => HandlerStack::create($handler)]), 'synthetic-key');
    foreach ([DriveSource::class, DrivePreviewSource::class, DriveRangeSource::class] as $contract) {
        $app->instance($contract, $source);
    }
});
$app->handleRequest(Request::capture());
