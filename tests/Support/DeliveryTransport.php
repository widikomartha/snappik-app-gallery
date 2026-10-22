<?php

namespace Tests\Support;

use App\Contracts\ArchiveStorage;
use App\Contracts\DeliveryArchiveStorage;
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

class DeliveryTransport
{
    public static function bind($app): void
    {
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
                return DriveFixtures::json([...$entry->metadata, 'thumbnailLink' => 'https://lh3.googleusercontent.com/synthetic-image']);
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
        $disk = new ArchiveDisk;
        $app->instance(ArchiveStorage::class, $disk);
        $app->instance(DeliveryArchiveStorage::class, $disk);
    }
}
