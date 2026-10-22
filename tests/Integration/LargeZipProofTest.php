<?php

namespace Tests\Integration;

use App\Contracts\DriveSource;
use App\Integrations\ArchiveStorage\R2ArchiveStorage;
use App\Services\Archives\BuildStreamingZip;
use App\Services\Drive\DriveItem;
use App\Services\Drive\DrivePage;
use App\Services\Drive\DriveReference;
use GuzzleHttp\Psr7\PumpStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Tests\Support\R2HttpFixture;
use ZipArchive;

class LargeZipProofTest extends TestCase
{
    public function test_source_above_four_gib_is_one_extractable_zip64_with_bounded_memory(): void
    {
        if (getenv('SNAPPIK_LARGE_PROOF') !== '1') {
            $this->markTestSkipped('Opt in with SNAPPIK_LARGE_PROOF=1; writes and reads over 4 GiB of temporary data.');
        }
        $size = 4 * 1024 ** 3 + 1024 ** 2;
        $path = tempnam(sys_get_temp_dir(), 'snappik-zip64-');
        $source = new class implements DriveSource
        {
            public array $hashes = [];

            public function metadata(DriveReference $reference): DriveItem
            {
                throw new \LogicException('Synthetic source has no provider');
            }

            public function children(DriveReference $folder, ?string $pageToken = null): DrivePage
            {
                throw new \LogicException('Synthetic source has no provider');
            }

            public function original(DriveItem $expected): StreamInterface
            {
                $remaining = $expected->size;
                $hash = hash_init('md5');

                return new PumpStream(function (int $length) use (&$remaining, $hash, $expected) {
                    if ($remaining === 0) {
                        $this->hashes[$expected->reference->id] ??= hash_final($hash);

                        return false;
                    }
                    $chunk = str_repeat('z', min($remaining, $length));
                    $remaining -= strlen($chunk);
                    hash_update($hash, $chunk);

                    return $chunk;
                }, ['size' => $expected->size]);
            }
        };
        $fixture = new R2HttpFixture($path);
        $builder = new BuildStreamingZip($source, new R2ArchiveStorage($fixture->client(), 'synthetic-private-bucket'));
        $started = microtime(true);
        try {
            $result = $builder->build('proofs/11111111-1111-1111-1111-111111111111/22222222-2222-2222-2222-222222222222.zip',
                [['path' => 'large__large.jpg', 'item' => new DriveItem(new DriveReference('large'), 'large.jpg', 'image/jpeg', $size, '1', canDownload: true)],
                    ['path' => 'small__small.jpg', 'item' => new DriveItem(new DriveReference('small'), 'small.jpg', 'image/jpeg', 31, '1', canDownload: true)]],
                2, $size + 31, $size + 4096);
            $buildSeconds = microtime(true) - $started;
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $this->assertSame(2, $zip->numFiles);
            $this->assertSame($size, $zip->statName('large__large.jpg')['size']);
            $extractionStarted = microtime(true);
            foreach (['large__large.jpg' => 'large', 'small__small.jpg' => 'small'] as $name => $id) {
                $entry = $zip->getStream($name);
                $md5 = hash_init('md5');
                $crc = hash_init('crc32b');
                $read = 0;
                while (! feof($entry)) {
                    $chunk = fread($entry, 1024 ** 2);
                    if ($chunk === false) {
                        throw new \RuntimeException('ZIP entry extraction failed');
                    }
                    $read += strlen($chunk);
                    hash_update($md5, $chunk);
                    hash_update($crc, $chunk);
                }
                fclose($entry);
                $this->assertSame($id === 'large' ? $size : 31, $read);
                $this->assertSame($source->hashes[$id], hash_final($md5));
                $this->assertSame(sprintf('%08x', $zip->statName($name)['crc']), hash_final($crc));
            }
            $zip->close();
            $tail = fopen($path, 'rb');
            fseek($tail, -1024, SEEK_END);
            $this->assertStringContainsString("PK\x06\x06", stream_get_contents($tail));
            fclose($tail);
            $metrics = $result + ['build_seconds' => round($buildSeconds, 3), 'extract_seconds' => round(microtime(true) - $extractionStarted, 3),
                'peak_php_bytes' => memory_get_peak_usage(true), 'parts' => count($fixture->partSizes), 'part_bytes' => $fixture->partSizes[0],
                'transport' => 'real AWS SDK with local synthetic HTTP fixture, not Cloudflare R2'];
            $this->assertLessThan(128 * 1024 ** 2, $metrics['peak_php_bytes']);
            file_put_contents(dirname(__DIR__, 2).'/storage/logs/large-zip-proof.json', json_encode($metrics, JSON_PRETTY_PRINT));
            fwrite(STDOUT, "\nZIP64_PROFILE ".json_encode($metrics)."\n");
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
