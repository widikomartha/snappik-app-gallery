<?php

namespace App\Integrations\ArchiveStorage;

use App\Contracts\ArchiveUpload;
use App\Services\ProviderFailure;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;

final class R2MultipartUpload implements ArchiveUpload
{
    private string $uploadId;

    private array $parts = [];

    private int $buffered = 0;

    private int $bytes = 0;

    private bool $completed = false;

    private bool $closed = false;

    /** @var resource */
    private $buffer;

    public function __construct(private S3Client $client, private string $bucket, private string $key, private int $partSize)
    {
        $this->buffer = fopen('php://temp/maxmemory:1048576', 'w+b');
        if ($this->buffer === false) {
            throw new ProviderFailure('archive_temporary_disk_unavailable');
        }
        try {
            // No ACL/public URL. The deployment bucket must be private.
            $result = $client->createMultipartUpload(['Bucket' => $bucket, 'Key' => $key, 'ContentType' => 'application/zip', 'CacheControl' => 'private, no-store']);
            $this->uploadId = (string) $result['UploadId'];
            if ($this->uploadId === '') {
                throw new ProviderFailure('r2_invalid_upload_response');
            }
        } catch (\Throwable $error) {
            fclose($this->buffer);
            throw $this->failure($error);
        }
    }

    public function write(string $bytes): int
    {
        if ($this->closed) {
            throw new ProviderFailure('archive_upload_closed');
        }
        $length = strlen($bytes);
        if ($this->bytes + $length > R2ArchiveStorage::MAXIMUM_OBJECT_BYTES) {
            throw new ProviderFailure('r2_native_object_capacity_exceeded');
        }
        $offset = 0;
        while ($offset < $length) {
            $chunk = substr($bytes, $offset, min($this->partSize - $this->buffered, $length - $offset));
            $written = fwrite($this->buffer, $chunk);
            if ($written !== strlen($chunk)) {
                throw new ProviderFailure('archive_temporary_disk_write_failed');
            }
            $this->buffered += $written;
            $this->bytes += $written;
            $offset += $written;
            if ($this->buffered === $this->partSize) {
                $this->uploadPart();
            }
        }

        return $length;
    }

    public function complete(): int
    {
        try {
            if ($this->buffered > 0) {
                $this->uploadPart();
            }
            $this->client->completeMultipartUpload($this->identity() + ['MultipartUpload' => ['Parts' => $this->parts]]);
            $this->completed = true;
            $head = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $this->key]);
            if ((int) $head['ContentLength'] !== $this->bytes || $head['ContentType'] !== 'application/zip') {
                throw new ProviderFailure('r2_completed_object_mismatch');
            }
            $this->closed = true;
            fclose($this->buffer);

            return $this->bytes;
        } catch (\Throwable $error) {
            throw $this->failure($error);
        }
    }

    public function abort(): void
    {
        $failed = false;
        // A timeout on Complete is ambiguous: abort and delete the attempt's unique key.
        if (! $this->completed) {
            try {
                $this->client->abortMultipartUpload($this->identity());
            } catch (AwsException $error) {
                $failed = $error->getAwsErrorCode() !== 'NoSuchUpload';
            } catch (\Throwable) {
                $failed = true;
            }
        }
        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $this->key]);
        } catch (\Throwable) {
            $failed = true;
        } finally {
            if (is_resource($this->buffer)) {
                fclose($this->buffer);
            }
            $this->closed = true;
        }
        if ($failed) {
            throw new ProviderFailure('r2_cleanup_failed', retryable: true);
        }
    }

    private function uploadPart(): void
    {
        if (count($this->parts) >= 10000) {
            throw new ProviderFailure('r2_native_part_capacity_exceeded');
        }
        rewind($this->buffer);
        try {
            $number = count($this->parts) + 1;
            $hash = hash_init('md5');
            hash_update_stream($hash, $this->buffer);
            rewind($this->buffer);
            $result = $this->client->uploadPart($this->identity() + ['PartNumber' => $number, 'Body' => $this->buffer, 'ContentLength' => $this->buffered,
                'ContentMD5' => base64_encode(hash_final($hash, true))]);
            if (empty($result['ETag'])) {
                throw new ProviderFailure('r2_invalid_part_response');
            }
            $this->parts[] = ['PartNumber' => $number, 'ETag' => $result['ETag']];
        } catch (\Throwable $error) {
            throw $this->failure($error);
        }
        ftruncate($this->buffer, 0);
        rewind($this->buffer);
        $this->buffered = 0;
    }

    private function identity(): array
    {
        return ['Bucket' => $this->bucket, 'Key' => $this->key, 'UploadId' => $this->uploadId];
    }

    private function failure(\Throwable $error): ProviderFailure
    {
        if ($error instanceof ProviderFailure) {
            return $error;
        }
        $status = $error instanceof AwsException ? $error->getStatusCode() : null;

        return new ProviderFailure('r2_request_failed', $status, $status === null || $status === 429 || $status >= 500);
    }
}
