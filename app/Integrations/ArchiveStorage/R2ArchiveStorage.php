<?php

namespace App\Integrations\ArchiveStorage;

use App\Contracts\ArchiveUpload;
use App\Contracts\DeliveryArchiveStorage;
use App\Services\Drive\ByteRange;
use App\Services\ProviderFailure;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\PumpStream;
use Psr\Http\Message\StreamInterface;

final class R2ArchiveStorage implements DeliveryArchiveStorage
{
    // R2 native service capacity, not a subscription/gallery quota.
    public const MAXIMUM_OBJECT_BYTES = 5 * 1024 ** 4 - 5 * 1024 ** 3;

    public function __construct(private S3Client $client, private string $bucket) {}

    public function begin(string $key, int $maximumBytes): ArchiveUpload
    {
        if (! preg_match('~\A(?:proofs|archives)/[a-f0-9-]{36}/[a-f0-9-]{36}\.zip\z~', $key)) {
            throw new ProviderFailure('archive_invalid_private_key');
        }
        if ($maximumBytes > self::MAXIMUM_OBJECT_BYTES) {
            throw new ProviderFailure('r2_native_object_capacity_exceeded');
        }
        // All nonfinal parts have the same size. Spill one part to temporary disk,
        // rather than raising the RAM budget for multi-terabyte objects.
        $partSize = max(8 * 1024 ** 2, (int) ceil($maximumBytes / 9999 / 1024 ** 2) * 1024 ** 2);

        return new R2MultipartUpload($this->client, $this->bucket, $key, $partSize);
    }

    public function delete(string $key): void
    {
        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
        } catch (\Throwable) {
            throw new ProviderFailure('r2_cleanup_failed', retryable: true);
        }
    }

    public function read(string $key): StreamInterface
    {
        try {
            $head = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]);
            $size = (int) ($head['ContentLength'] ?? 0);
            $etag = $head['ETag'] ?? null;
            if ($size < 1 || ! is_string($etag) || $etag === '') {
                throw new ProviderFailure('r2_read_invalid_response');
            }
            $offset = 0;

            // Keep each response short even when the complete ZIP takes minutes
            // to read. These are ranges of one object, never separate ZIP files.
            return new PumpStream(function () use ($key, $size, $etag, &$offset): string|false {
                if ($offset === $size) {
                    return false;
                }
                $end = min($size - 1, $offset + 8 * 1024 ** 2 - 1);
                $bytes = $this->readChunk($key, $offset, $end, $size, $etag);
                $offset = $end + 1;

                return $bytes;
            }, ['size' => $size]);
        } catch (\Throwable $error) {
            throw $error instanceof ProviderFailure ? $error : new ProviderFailure('r2_read_failed', retryable: true);
        }
    }

    public function slice(string $key, ByteRange $range, int $expectedSize): StreamInterface
    {
        try {
            $head = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]);
            $etag = $head['ETag'] ?? null;
            if ((int) ($head['ContentLength'] ?? -1) !== $expectedSize || ($head['ContentType'] ?? '') !== 'application/zip'
                || ! is_string($etag) || $etag === '' || $range->start < 0 || $range->end >= $expectedSize) {
                throw new ProviderFailure('r2_read_invalid_response');
            }
            $offset = $range->start;

            return new PumpStream(function () use ($key, $range, $expectedSize, $etag, &$offset): string|false {
                if ($offset > $range->end) {
                    return false;
                }
                $end = min($range->end, $offset + 8 * 1024 ** 2 - 1);
                $bytes = $this->readChunk($key, $offset, $end, $expectedSize, $etag);
                $offset = $end + 1;

                return $bytes;
            }, ['size' => $range->length()]);
        } catch (\Throwable $error) {
            throw $error instanceof ProviderFailure ? $error : new ProviderFailure('r2_read_failed', retryable: true);
        }
    }

    public function cleanup(string $key): void
    {
        // Never sweep a bucket or a broad prefix. Attempt identities cannot name backups.
        if (! preg_match('~\Aarchives/[a-f0-9-]{36}/[a-f0-9-]{36}\.zip\z~', $key)) {
            throw new ProviderFailure('archive_invalid_private_key');
        }
        try {
            $markers = [];
            do {
                $page = $this->client->listMultipartUploads(['Bucket' => $this->bucket, 'Prefix' => $key, 'MaxUploads' => 100] + $markers);
                foreach ($page['Uploads'] ?? [] as $upload) {
                    if ($upload['Key'] === $key) {
                        try {
                            $this->client->abortMultipartUpload(['Bucket' => $this->bucket, 'Key' => $key, 'UploadId' => $upload['UploadId']]);
                        } catch (AwsException $error) {
                            if ($error->getAwsErrorCode() !== 'NoSuchUpload') {
                                throw $error;
                            }
                        }
                    }
                }
                $next = ['KeyMarker' => $page['NextKeyMarker'] ?? '', 'UploadIdMarker' => $page['NextUploadIdMarker'] ?? ''];
                if (($page['IsTruncated'] ?? false) && ($next === $markers || $next['KeyMarker'] === '')) {
                    throw new ProviderFailure('r2_cleanup_invalid_page');
                }
                $markers = $next;
            } while ($page['IsTruncated'] ?? false);
            $this->delete($key);
        } catch (\Throwable) {
            throw new ProviderFailure('r2_cleanup_failed', retryable: true);
        }
    }

    private function readChunk(string $key, int $start, int $end, int $size, string $etag): string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->fetchRange($key, $start, $end, $size, $etag);
            } catch (ProviderFailure $error) {
                if (! $error->retryable || $attempt === 3) {
                    throw $error;
                }
                // No bytes escape a failed range. Retry the same pinned version.
                usleep(250000 * $attempt);
            }
        }
        throw new ProviderFailure('r2_read_failed', retryable: true);
    }

    private function fetchRange(string $key, int $start, int $end, int $size, string $etag): string
    {
        $body = null;
        try {
            $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $key,
                'Range' => 'bytes='.$start.'-'.$end, 'IfMatch' => $etag,
                // Buffer only this bounded response, allowing the SDK's normal
                // transport to finish/retry it instead of returning a live PHP
                // socket stream for the entire object.
                '@http' => ['stream' => false, 'timeout' => 300, 'read_timeout' => 60]]);
            $body = $result['Body'];
            $length = $end - $start + 1;
            if (($result['@metadata']['statusCode'] ?? null) !== 206
                || ($result['ContentRange'] ?? null) !== 'bytes '.$start.'-'.$end.'/'.$size
                || (int) ($result['ContentLength'] ?? -1) !== $length || ($result['ETag'] ?? null) !== $etag) {
                throw new ProviderFailure('r2_read_invalid_response');
            }
            $bytes = '';
            while (! $body->eof()) {
                $chunk = $body->read(min(1024 ** 2, $length - strlen($bytes) + 1));
                if (($chunk === '' && ! $body->eof()) || strlen($bytes) + strlen($chunk) > $length) {
                    throw new ProviderFailure('r2_read_invalid_response');
                }
                $bytes .= $chunk;
            }
            if (strlen($bytes) !== $length) {
                throw new ProviderFailure('r2_read_invalid_response');
            }

            return $bytes;
        } catch (\Throwable $error) {
            $status = $error instanceof AwsException ? $error->getStatusCode() : null;
            $retryable = $status === null || $status === 429 || $status >= 500;
            throw $error instanceof ProviderFailure ? $error : new ProviderFailure('r2_read_failed', retryable: $retryable);
        } finally {
            $body?->close();
        }
    }
}
