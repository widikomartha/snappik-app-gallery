<?php

namespace App\Integrations\ArchiveStorage;

use App\Contracts\ArchiveStorage;
use App\Contracts\ArchiveUpload;
use App\Services\ProviderFailure;
use Aws\S3\S3Client;
use Psr\Http\Message\StreamInterface;

final class R2ArchiveStorage implements ArchiveStorage
{
    // R2 native service capacity, not a subscription/gallery quota.
    public const MAXIMUM_OBJECT_BYTES = 5 * 1024 ** 4 - 5 * 1024 ** 3;

    public function __construct(private S3Client $client, private string $bucket) {}

    public function begin(string $key, int $maximumBytes): ArchiveUpload
    {
        if (! preg_match('~\Aproofs/[a-f0-9-]{36}/[a-f0-9-]{36}\.zip\z~', $key)) {
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
            $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $key, '@http' => ['stream' => true]]);

            return $result['Body'];
        } catch (\Throwable) {
            throw new ProviderFailure('r2_read_failed', retryable: true);
        }
    }
}
