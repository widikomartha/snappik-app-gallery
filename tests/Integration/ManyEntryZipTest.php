<?php

namespace Tests\Integration;

use App\Services\Archives\BoundedZip;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ManyEntryZipTest extends TestCase
{
    public function test_directory_spools_to_disk_and_zip64_preserves_more_than_65535_entries(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'snappik-many-');
        $target = fopen($path, 'w+b');
        $before = memory_get_usage(true);
        $peak = $before;
        try {
            $zip = new BoundedZip(function (string $bytes) use ($target) {
                fwrite($target, $bytes);
            });
            for ($i = 0; $i < 66000; $i++) {
                $stream = Utils::streamFor('x');
                $zip->add('same__'.$i.'.jpg', $stream, 1);
                $stream->close();
                if ($i % 1000 === 0) {
                    $peak = max($peak, memory_get_usage(true));
                }
            }
            $zip->finish();
            unset($zip);
            fclose($target);
            $growth = memory_get_usage(true) - $before;
            $this->assertLessThan(16 * 1024 ** 2, $growth);
            $this->assertLessThan(8 * 1024 ** 2, $peak - $before);
            $reader = new ZipArchive;
            $this->assertTrue($reader->open($path));
            $this->assertSame(66000, $reader->numFiles);
            $this->assertSame('x', $reader->getFromName('same__0.jpg'));
            $this->assertSame('x', $reader->getFromName('same__65999.jpg'));
            $reader->close();
            fwrite(STDOUT, "\nMANY_ENTRY_PROFILE ".json_encode(['entries' => 66000, 'php_retained_growth_bytes' => $growth,
                'php_sampled_growth_bytes' => $peak - $before, 'peak_php_bytes' => memory_get_peak_usage(true)])."\n");
        } finally {
            if (is_resource($target)) {
                fclose($target);
            }
            unlink($path);
        }
    }
}
