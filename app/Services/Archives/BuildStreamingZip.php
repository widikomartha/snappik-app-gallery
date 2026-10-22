<?php

namespace App\Services\Archives;

use App\Contracts\ArchiveStorage;
use App\Contracts\DriveSource;
use App\Services\Drive\DriveItem;
use App\Services\ProviderFailure;
use GuzzleHttp\Psr7\FnStream;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

final class BuildStreamingZip
{
    public function __construct(private DriveSource $drive, private ArchiveStorage $storage) {}

    /**
     * Entries come from the immutable, scoped manifest in stable path/identity order.
     *
     * @param  iterable<array{item: DriveItem, path: string}>  $entries
     */
    public function build(string $key, iterable $entries, int $expectedCount, int $expectedBytes, int $maximumZipBytes, ?\Closure $progress = null): array
    {
        if ($expectedCount < 1) {
            throw new ProviderFailure('archive_empty_manifest');
        }
        $upload = $this->storage->begin($key, $maximumZipBytes);
        $outputFailure = null;
        $output = new FnStream([
            'isReadable' => fn () => false, 'isWritable' => fn () => true,
            'write' => function (string $bytes) use ($upload, &$outputFailure): int {
                try {
                    return $upload->write($bytes);
                } catch (\Throwable $error) {
                    $outputFailure = $error;
                    throw $error;
                }
            },
        ]);
        $count = 0;
        $bytes = 0;
        try {
            $zip = new ZipStream(outputStream: $output, defaultCompressionMethod: CompressionMethod::STORE,
                enableZip64: true, defaultEnableZeroHeader: true, sendHttpHeaders: false);
            foreach ($entries as $entry) {
                $item = $entry['item'];
                $stream = $this->drive->original($item);
                try {
                    $zip->addFileFromPsr7Stream(fileName: $entry['path'], stream: $stream, exactSize: $item->size);
                    // The library reads exactSize. Check surplus bytes and call the source's
                    // verifier directly, since PSR stream wrappers may swallow exceptions.
                    if ($stream->read(1) !== '' || ! $stream->eof()) {
                        throw new ProviderFailure('drive_media_integrity_failed');
                    }
                } finally {
                    $stream->close();
                }
                if ($outputFailure !== null) {
                    throw $outputFailure;
                }
                $count++;
                $bytes += $item->size;
                $progress?->__invoke($count, $bytes);
            }
            if ($count !== $expectedCount || $bytes !== $expectedBytes) {
                throw new ProviderFailure('archive_manifest_mismatch');
            }
            $zipBytes = $zip->finish();
            if ($outputFailure !== null) {
                throw $outputFailure;
            }
            if ($upload->complete() !== $zipBytes) {
                throw new ProviderFailure('archive_output_length_mismatch');
            }

            return ['files' => $count, 'source_bytes' => $bytes, 'zip_bytes' => $zipBytes];
        } catch (\Throwable $error) {
            try {
                $upload->abort();
            } catch (\Throwable) {
                throw new ProviderFailure('archive_cleanup_required', retryable: true);
            }
            throw $error instanceof ProviderFailure ? $error : new ProviderFailure('archive_stream_failed');
        }
    }
}
