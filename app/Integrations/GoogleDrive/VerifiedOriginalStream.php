<?php

namespace App\Integrations\GoogleDrive;

use App\Services\Drive\ByteRange;
use App\Services\Drive\DriveItem;
use App\Services\ProviderFailure;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

final class VerifiedOriginalStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $bytes = 0;

    private \HashContext $hash;

    private bool $verified = false;

    private string $prefix = '';

    private ?ProviderFailure $failure = null;

    public function __construct(StreamInterface $stream, private DriveItem $expected, private \Closure $afterTransfer, private ?ByteRange $range = null)
    {
        $this->stream = $stream;
        $this->hash = hash_init('md5');
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new ProviderFailure('drive_original_not_seekable');
    }

    public function rewind(): void
    {
        throw new ProviderFailure('drive_original_not_seekable');
    }

    public function read(int $length): string
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->verified) {
            return '';
        }
        try {
            $chunk = $this->stream->read($length);
            if (($this->range === null || $this->range->start === 0) && strlen($this->prefix) < 64) {
                $this->prefix .= substr($chunk, 0, 64 - strlen($this->prefix));
                if (preg_match('/\A\s*(?:<!doctype\s+html|<html|<\?xml)/i', $this->prefix)) {
                    throw new ProviderFailure('drive_invalid_media_response');
                }
            }
            $this->bytes += strlen($chunk);
            hash_update($this->hash, $chunk);
            if ($this->bytes > ($this->range?->length() ?? $this->expected->size) || ($chunk === '' && ! $this->stream->eof() && $length > 0)) {
                throw new ProviderFailure('drive_media_integrity_failed');
            }
            if ($this->stream->eof()) {
                $this->verify();
            }

            return $chunk;
        } catch (\Throwable $error) {
            throw $this->failure = $error instanceof ProviderFailure ? $error : new ProviderFailure('drive_transfer_failed', retryable: true);
        }
    }

    public function eof(): bool
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->stream->eof()) {
            $this->verify();

            return true;
        }

        return false;
    }

    public function getContents(): string
    {
        return Utils::copyToString($this);
    }

    private function verify(): void
    {
        if ($this->verified) {
            return;
        }
        try {
            if ($this->bytes !== ($this->range?->length() ?? $this->expected->size) || ($this->range === null && $this->expected->md5 !== null && ! hash_equals(strtolower($this->expected->md5), hash_final($this->hash)))) {
                throw new ProviderFailure('drive_media_integrity_failed');
            }
            ($this->afterTransfer)();
            $this->verified = true;
        } catch (\Throwable $error) {
            throw $this->failure = $error instanceof ProviderFailure ? $error : new ProviderFailure('drive_transfer_failed', retryable: true);
        }
    }
}
