<?php

namespace Tests\Integration;

use App\Integrations\ArchiveStorage\R2ArchiveStorage;
use App\Services\ProviderFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\R2HttpFixture;

class R2ArchiveStorageTest extends TestCase
{
    private string $path;

    private const KEY = 'proofs/11111111-1111-1111-1111-111111111111/22222222-2222-2222-2222-222222222222.zip';

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
}
