<?php

namespace Tests\Support;

use App\Contracts\ArchiveUpload;
use App\Contracts\DeliveryArchiveStorage;
use App\Services\Drive\ByteRange;
use App\Services\ProviderFailure;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

/** Synthetic private disk storage. Never registered by production code. */
class ArchiveDisk implements DeliveryArchiveStorage
{
    public array $keys = [];

    public array $cleaned = [];

    public bool $failCleanup = false;

    public ?\Closure $beforeComplete = null;

    public function path(string $key): string
    {
        return storage_path('framework/testing/archive-'.hash('sha256', $key).'.zip');
    }

    public function begin(string $key, int $maximumBytes): ArchiveUpload
    {
        $this->keys[] = $key;
        $path = $this->path($key);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        return new class($path, $this->beforeComplete) implements ArchiveUpload
        {
            private $stream;

            public function __construct(private string $path, private ?\Closure $beforeComplete)
            {
                $this->stream = fopen($path, 'w+b');
            }

            public function write(string $bytes): int
            {
                return fwrite($this->stream, $bytes);
            }

            public function complete(): int
            {
                $size = ftell($this->stream);
                fclose($this->stream);
                $this->beforeComplete?->__invoke();

                return $size;
            }

            public function abort(): void
            {
                if (is_resource($this->stream)) {
                    fclose($this->stream);
                }
                if (is_file($this->path)) {
                    unlink($this->path);
                }
            }
        };
    }

    public function read(string $key): StreamInterface
    {
        return Utils::streamFor(fopen($this->path($key), 'rb'));
    }

    public function slice(string $key, ByteRange $range, int $expectedSize): StreamInterface
    {
        if (! is_file($this->path($key)) || filesize($this->path($key)) !== $expectedSize) {
            throw new ProviderFailure('r2_read_failed');
        }

        return new LimitStream($this->read($key), $range->length(), $range->start);
    }

    public function delete(string $key): void
    {
        if (is_file($this->path($key))) {
            unlink($this->path($key));
        }
    }

    public function cleanup(string $key): void
    {
        if ($this->failCleanup) {
            throw new ProviderFailure('r2_cleanup_failed');
        }
        $this->cleaned[] = $key;
        $this->delete($key);
    }
}
