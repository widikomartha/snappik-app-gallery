<?php

namespace Tests\Integration;

use App\Integrations\GoogleDrive\PublicDriveSource;
use App\Services\Drive\DriveItem;
use App\Services\Drive\DriveReference;
use App\Services\ProviderFailure;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\DriveFixtures as F;

class PublicDriveSourceTest extends TestCase
{
    private array $history = [];

    private function source(array $responses, ?array &$delays = null): PublicDriveSource
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new PublicDriveSource(new Client(['handler' => $stack]), 'synthetic-api-key', sleep: function ($ms) use (&$delays) {
            $delays[] = $ms;
        });
    }

    public function test_folder_url_parser_and_resource_key(): void
    {
        $reference = DriveReference::fromFolderUrl('https://drive.google.com/drive/u/0/folders/synthetic-root?resourcekey=synthetic-key&usp=sharing');
        $this->assertSame('synthetic-root', $reference->id);
        $this->assertSame('synthetic-key', $reference->resourceKey);
    }

    public static function unsafeUrls(): array
    {
        return array_map(fn ($url) => [$url], ['http://drive.google.com/drive/folders/root', 'https://evil.test/drive/folders/root',
            'https://drive.google.com.evil.test/drive/folders/root', 'https://user@drive.google.com/drive/folders/root',
            'https://drive.google.com:443/drive/folders/root', 'https://drive.google.com/file/d/photo/view',
            'https://drive.google.com/drive/folders/root?resourcekey[]=bad', 'file:///etc/passwd',
            'https://drive.google.com/drive/folders/root%2fother']);
    }

    #[DataProvider('unsafeUrls')]
    public function test_rejects_unsafe_source_urls(string $url): void
    {
        $this->expectException(ProviderFailure::class);
        DriveReference::fromFolderUrl($url);
    }

    public function test_official_pagination_headers_and_original_bytes(): void
    {
        $photo = F::photo();
        $source = $this->source([F::json(['files' => [$photo], 'nextPageToken' => 'page2']), F::json(['files' => []]),
            F::json($photo), new Response(200, ['Content-Type' => 'image/jpeg', 'Content-Length' => '12'], 'original-one'), F::json($photo)]);
        $root = new DriveReference('root', 'synthetic-resource-key');
        $page = $source->children($root);
        $this->assertSame('page2', $page->nextPageToken);
        $this->assertNull($source->children($root, $page->nextPageToken)->nextPageToken);
        $stream = $source->original($page->items[0]);
        $bytes = '';
        while (! $stream->eof()) {
            $bytes .= $stream->read(4);
        }
        $stream->close();
        $this->assertSame('original-one', $bytes);
        $request = $this->history[0]['request'];
        $this->assertSame('www.googleapis.com', $request->getUri()->getHost());
        $this->assertSame('root/synthetic-resource-key', $request->getHeaderLine('X-Goog-Drive-Resource-Keys'));
        $this->assertSame('synthetic-api-key', $request->getHeaderLine('X-Goog-Api-Key'));
        $this->assertStringNotContainsString('synthetic-api-key', (string) $request->getUri());
        $this->assertFalse($this->history[0]['options']['allow_redirects']);
        $this->assertTrue($this->history[3]['options']['stream']);
        $this->assertStringContainsString('pageToken=page2', (string) $this->history[1]['request']->getUri());
    }

    public function test_native_failure_is_sanitized_and_temporary_errors_retry(): void
    {
        $delays = [];
        $source = $this->source([new Response(429), new Response(503), F::json(F::folder())], $delays);
        $this->assertSame('root', $source->metadata(new DriveReference('root'))->reference->id);
        $this->assertCount(2, $delays);
        $source = $this->source([new Response(404, [], 'secret-provider-body')]);
        try {
            $source->metadata(new DriveReference('root', 'synthetic-resource-key'));
            $this->fail('Expected provider failure');
        } catch (ProviderFailure $error) {
            $this->assertSame('drive_not_found_or_resource_key_required (HTTP 404)', $error->getMessage());
            $this->assertNull($error->getPrevious());
            $this->assertFalse($error->retryable);
        }
    }

    public function test_redirect_is_never_followed(): void
    {
        $source = $this->source([new Response(302, ['Location' => 'http://127.0.0.1/private'])]);
        $this->expectExceptionMessage('drive_redirect_rejected');
        $source->metadata(new DriveReference('root'));
    }

    public static function badMedia(): array
    {
        return [
            'html type' => ['text/html', 'original-one', null],
            'html disguised' => ['image/jpeg', '<html>login!', null],
            'truncated' => ['image/jpeg', 'short', null],
            'wrong checksum' => ['image/jpeg', 'original-two', null],
            'surplus' => ['image/jpeg', 'original-one-extra', null],
            'wrong content length' => ['image/jpeg', 'original-one', '13'],
        ];
    }

    #[DataProvider('badMedia')]
    public function test_invalid_media_is_rejected(string $type, string $bytes, ?string $length): void
    {
        $photo = F::photo();
        $source = $this->source([F::json($photo), new Response(200, array_filter(['Content-Type' => $type, 'Content-Length' => $length]), $bytes), F::json($photo)]);
        $this->expectException(ProviderFailure::class);
        $stream = $source->original(DriveItem::fromArray($photo));
        try {
            while (! $stream->eof()) {
                $stream->read(1024);
            }
        } finally {
            $stream->close();
        }
    }

    public function test_changed_source_and_download_restriction_fail_before_bytes(): void
    {
        $photo = F::photo();
        $changed = $photo;
        $changed['version'] = '8';
        $source = $this->source([F::json($changed)]);
        $this->expectExceptionMessage('drive_source_changed');
        $source->original(DriveItem::fromArray($photo));
    }

    public function test_download_permission_is_required(): void
    {
        $photo = F::photo();
        $photo['capabilities']['canDownload'] = false;
        $this->expectExceptionMessage('drive_download_restricted');
        $this->source([F::json($photo)])->original(DriveItem::fromArray($photo));
    }

    public function test_missing_credentials_make_no_http_request(): void
    {
        $this->expectExceptionMessage('drive_credentials_missing');
        (new PublicDriveSource(new Client, ''))->metadata(new DriveReference('root'));
    }

    public function test_stream_get_contents_cannot_bypass_integrity_verification(): void
    {
        $photo = F::photo();
        $source = $this->source([F::json($photo), new Response(200, ['Content-Type' => 'image/jpeg'], 'original-BAD')]);
        $stream = $source->original(DriveItem::fromArray($photo));
        try {
            $this->expectExceptionMessage('drive_media_integrity_failed');
            $stream->getContents();
        } finally {
            $stream->close();
        }
    }

    public function test_change_during_transfer_fails_at_eof(): void
    {
        $photo = F::photo();
        $changed = $photo;
        $changed['version'] = '8';
        $stream = $this->source([F::json($photo), new Response(200, ['Content-Type' => 'image/jpeg'], 'original-one'), F::json($changed)])
            ->original(DriveItem::fromArray($photo));
        try {
            $this->expectExceptionMessage('drive_source_changed');
            $stream->getContents();
        } finally {
            $stream->close();
        }
    }

    public function test_incomplete_provider_search_is_not_a_complete_page(): void
    {
        $this->expectExceptionMessage('drive_incomplete_listing');
        $this->source([F::json(['files' => [], 'incompleteSearch' => true])])->children(new DriveReference('root'));
    }

    public function test_automated_query_block_is_sanitized_and_not_retried_or_served_as_media(): void
    {
        $photo = F::photo();
        $source = $this->source([F::json($photo), new Response(403, ['Content-Type' => 'text/html; charset=UTF-8'],
            '<html>Your computer or network may be sending automated queries. synthetic-private-marker</html>')]);
        try {
            $source->original(DriveItem::fromArray($photo));
            $this->fail('Expected automated request block');
        } catch (ProviderFailure $error) {
            $this->assertSame('drive_automated_requests_blocked', $error->reason);
            $this->assertSame(403, $error->providerStatus);
            $this->assertFalse($error->retryable);
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString('synthetic-private-marker', $error->getMessage());
            $this->assertCount(2, $this->history);
        }
    }

    public function test_streamed_media_quota_response_has_a_precise_terminal_failure(): void
    {
        $photo = F::photo();
        $source = $this->source([F::json($photo), F::json(['error' => ['errors' => [['reason' => 'downloadQuotaExceeded']]]], 403)]);
        try {
            $source->original(DriveItem::fromArray($photo));
            $this->fail('Expected download quota failure');
        } catch (ProviderFailure $error) {
            $this->assertSame('drive_download_quota_exceeded', $error->reason);
            $this->assertFalse($error->retryable);
            $this->assertCount(2, $this->history);
        }
    }

    public function test_streamed_media_rate_limit_retries_within_the_existing_budget(): void
    {
        $photo = F::photo();
        $delays = [];
        $source = $this->source([F::json($photo), F::json(['error' => ['errors' => [['reason' => 'rateLimitExceeded']]]], 403),
            new Response(200, ['Content-Type' => 'image/jpeg'], 'original-one'), F::json($photo)], $delays);
        $body = $source->original(DriveItem::fromArray($photo));
        try {
            $this->assertSame('original-one', $body->getContents());
            $this->assertCount(1, $delays);
            $this->assertCount(4, $this->history);
        } finally {
            $body->close();
        }
    }
}
