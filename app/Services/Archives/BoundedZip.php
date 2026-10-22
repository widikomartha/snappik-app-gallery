<?php

namespace App\Services\Archives;

use App\Services\ProviderFailure;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\StreamWrapper;
use Psr\Http\Message\StreamInterface;
use ZipStream\CompressionMethod;
use ZipStream\EndOfCentralDirectory;
use ZipStream\File;
use ZipStream\OperationMode;
use ZipStream\Zip64;

/** Adapter to the locked ZipStream entry encoder. Spool directory records instead of retaining an array per photo. */
final class BoundedZip
{
    private $directory;

    private int $offset = 0;

    private int $count = 0;

    public function __construct(private \Closure $write)
    {
        $this->directory = fopen('php://temp/maxmemory:1048576', 'w+b');
        if ($this->directory === false) {
            throw new ProviderFailure('archive_temporary_disk_unavailable');
        }
    }

    public function add(string $path, StreamInterface $stream, int $size): void
    {
        // PHP stream wrappers can read ahead past fread's requested length. Keep surplus
        // bytes in the original PSR stream so the caller can reject them after exactSize.
        $limited = new LimitStream($stream, $size);
        $file = new File(fileName: $path, dataCallback: fn () => StreamWrapper::getResource($limited),
            operationMode: OperationMode::NORMAL, startOffset: $this->offset, compressionMethod: CompressionMethod::STORE,
            comment: '', lastModificationDateTime: new \DateTimeImmutable('2026-01-01T00:00:00Z'), deflateLevel: 0,
            maxSize: null, exactSize: $size, enableZip64: true, enableZeroHeader: true,
            send: $this->send(...), recordSentBytes: function (int $bytes) {
                $this->offset += $bytes;
            });
        $record = $file->process();
        if (fwrite($this->directory, $record) !== strlen($record)) {
            throw new ProviderFailure('archive_temporary_disk_write_failed');
        }
        $this->count++;
    }

    public function finish(): int
    {
        $start = $this->offset;
        rewind($this->directory);
        while (! feof($this->directory)) {
            $chunk = fread($this->directory, 65536);
            if ($chunk === false) {
                throw new ProviderFailure('archive_temporary_disk_read_failed');
            }
            $this->send($chunk);
        }
        $size = $this->offset - $start;
        if ($this->count >= 0xFFFF || $start > 0xFFFFFFFF || $size > 0xFFFFFFFF) {
            $this->send(Zip64\EndOfCentralDirectory::generate(versionMadeBy: 0x603, versionNeededToExtract: 45,
                numberOfThisDisk: 0, numberOfTheDiskWithCentralDirectoryStart: 0,
                numberOfCentralDirectoryEntriesOnThisDisk: $this->count, numberOfCentralDirectoryEntries: $this->count,
                sizeOfCentralDirectory: $size, centralDirectoryStartOffsetOnDisk: $start, extensibleDataSector: ''));
            $this->send(Zip64\EndOfCentralDirectoryLocator::generate(numberOfTheDiskWithZip64CentralDirectoryStart: 0,
                zip64centralDirectoryStartOffsetOnDisk: $start + $size, totalNumberOfDisks: 1));
        }
        $this->send(EndOfCentralDirectory::generate(numberOfThisDisk: 0, numberOfTheDiskWithCentralDirectoryStart: 0,
            numberOfCentralDirectoryEntriesOnThisDisk: min($this->count, 0xFFFF), numberOfCentralDirectoryEntries: min($this->count, 0xFFFF),
            sizeOfCentralDirectory: min($size, 0xFFFFFFFF), centralDirectoryStartOffsetOnDisk: min($start, 0xFFFFFFFF), zipFileComment: ''));

        return $this->offset;
    }

    private function send(string $bytes): void
    {
        ($this->write)($bytes);
        $this->offset += strlen($bytes);
    }

    public function __destruct()
    {
        if (is_resource($this->directory)) {
            fclose($this->directory);
        }
    }
}
