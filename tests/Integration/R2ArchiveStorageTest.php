<?php

namespace Tests\Integration;

use App\Integrations\ArchiveStorage\R2ArchiveStorage;
use App\Services\Drive\ByteRange;
use App\Services\ProviderFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\R2HttpFixture;

class R2ArchiveStorageTest extends TestCase
{
    private string $path;

    private const KEY = 'proofs/11111111-1111-1111-1111-111111111111/22222222-2222-2222-2222-222222222222.zip';

    public function test_product_range_pins_the_complete_object_and_scoped_cleanup_paginates_orphan_uploads(): void
    {
        $fixture = new R2HttpFixture($this->path);
        $storage = new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket');
        $key = str_replace('proofs/', 'archives/', self::KEY);
        $upload = $storage->begin($key, 100);
        $upload->write('complete archive bytes');
        $upload->complete();
        $stream = $storage->slice($key, new ByteRange(4, 12), 22);
        $this->assertSame('lete arch', $stream->getContents());
        $stream->close();
        $this->assertSame('bytes=4-12', $fixture->readRequests[0]['range']);
        $this->assertSame('"synthetic-complete"', $fixture->readRequests[0]['if_match']);
        $fixture->orphanKey = $key;
        $storage->cleanup($key);
        $this->assertSame(2, count(array_filter($fixture->operations, fn ($op) => $op === 'list')));
        $this->assertSame(2, count(array_filter($fixture->operations, fn ($op) => $op === 'abort')));
        $this->assertSame($key, $fixture->readRequests[1]['prefix']);
        $this->assertSame($key, $fixture->readRequests[2]['marker']);
        $this->assertFileDoesNotExist($this->path);
    }

    public function test_product_cleanup_cannot_target_a_backup_key(): void
    {
        $fixture = new R2HttpFixture($this->path);
        $storage = new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket');
        try {
            $storage->cleanup('backups/keep.zip');
            $this->fail('Expected scoped-key refusal');
        } catch (ProviderFailure $failure) {
            $this->assertSame('archive_invalid_private_key', $failure->reason);
        }
        $this->assertSame([], $fixture->operations);
    }

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'snappik-r2-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_incremental_multipart_writes_one_object_with_equal_nonfinal_parts(): void
    {
        $fixture = new R2HttpFixture($this->path);
        $upload = (new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'))->begin(self::KEY, 17 * 1024 ** 2);
        for ($i = 0; $i < 17; $i++) {
            $upload->write(str_repeat('x', 1024 ** 2));
        }
        $this->assertSame(17 * 1024 ** 2, $upload->complete());
        $this->assertSame([8 * 1024 ** 2, 8 * 1024 ** 2, 1024 ** 2], $fixture->partSizes);
        $this->assertSame(['create', 'part', 'part', 'part', 'complete', 'head'], $fixture->operations);
    }

    public static function failures(): array
    {
        return [['failPart'], ['failComplete'], ['wrongHead']];
    }

    #[DataProvider('failures')]
    public function test_failed_upload_or_final_validation_cleans_attempt(string $failure): void
    {
        $fixture = new R2HttpFixture($this->path);
        $fixture->{$failure} = $failure === 'failPart' ? 1 : true;
        $upload = (new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'))->begin(self::KEY, 1000);
        try {
            $upload->write('synthetic zip data');
            $upload->complete();
            $this->fail('Expected storage failure');
        } catch (ProviderFailure $error) {
            $upload->abort();
            $this->assertFileDoesNotExist($fixture->path);
            $this->assertContains('delete', $fixture->operations);
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString('synthetic-secret', $error->getMessage());
        }
    }

    public function test_native_capacity_failure_happens_before_provider_request(): void
    {
        $fixture = new R2HttpFixture($this->path);
        try {
            (new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'))->begin(self::KEY, R2ArchiveStorage::MAXIMUM_OBJECT_BYTES + 1);
            $this->fail('Expected native capacity failure');
        } catch (ProviderFailure $error) {
            $this->assertSame('r2_native_object_capacity_exceeded', $error->reason);
            $this->assertSame([], $fixture->operations);
        }
    }

    public function test_large_object_read_joins_bounded_ranges_of_the_same_etag(): void
    {
        $bytes = str_repeat('a', 8 * 1024 ** 2).'synthetic-tail';
        file_put_contents($this->path, $bytes);
        $fixture = new R2HttpFixture($this->path);
        $stream = (new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'))->read(self::KEY);
        $this->assertSame(strlen($bytes), $stream->getSize());
        $this->assertSame($bytes, $stream->getContents());
        $stream->close();
        $this->assertSame(['head', 'get', 'get'], $fixture->operations);
        $this->assertSame(['bytes=0-8388607', 'bytes=8388608-'.(strlen($bytes) - 1)], array_column($fixture->readRequests, 'range'));
        foreach ($fixture->readRequests as $request) {
            $this->assertSame('"synthetic-complete"', $request['if_match']);
            $this->assertFalse($request['stream']);
            $this->assertSame(300, $request['timeout']);
            $this->assertSame(60, $request['read_timeout']);
        }
    }

    public static function invalidRanges(): array
    {
        return [['status'], ['etag'], ['range'], ['truncated'], ['surplus']];
    }

    public function test_interrupted_range_is_retried_without_duplicating_bytes(): void
    {
        file_put_contents($this->path, 'synthetic-range');
        $fixture = new R2HttpFixture($this->path);
        $fixture->interruptedReads = 1;
        $stream = (new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'))->read(self::KEY);
        $this->assertSame('synthetic-range', $stream->getContents());
        $this->assertSame(['bytes=0-14', 'bytes=0-14'], array_column($fixture->readRequests, 'range'));
        $stream->close();
    }

    public function test_repeated_read_interruptions_have_a_bounded_sanitized_failure(): void
    {
        file_put_contents($this->path, 'synthetic-range');
        $fixture = new R2HttpFixture($this->path);
        $fixture->interruptedReads = 3;
        $stream = (new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'))->read(self::KEY);
        try {
            $stream->getContents();
            $this->fail('Expected unavailable read');
        } catch (ProviderFailure $error) {
            $this->assertSame('r2_read_failed', $error->reason);
            $this->assertTrue($error->retryable);
            $this->assertNull($error->getPrevious());
            $this->assertCount(3, $fixture->readRequests);
        } finally {
            $stream->close();
        }
    }

    #[DataProvider('invalidRanges')]
    public function test_changed_or_invalid_range_never_returns_unverified_bytes(string $failure): void
    {
        file_put_contents($this->path, 'synthetic-range');
        $fixture = new R2HttpFixture($this->path);
        $fixture->badRange = $failure;
        $stream = (new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'))->read(self::KEY);
        try {
            $stream->read(1);
            $this->fail('Expected invalid range failure');
        } catch (ProviderFailure $error) {
            $this->assertSame('r2_read_invalid_response', $error->reason);
            $this->assertNull($error->getPrevious());
        } finally {
            $stream->close();
        }
    }
}
