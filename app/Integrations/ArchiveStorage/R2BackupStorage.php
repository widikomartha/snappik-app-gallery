<?php

namespace App\Integrations\ArchiveStorage;

use App\Contracts\BackupStorage;
use App\Services\Operations\BackupConfiguration;
use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;

final class R2BackupStorage implements BackupStorage
{
    public function __construct(private ?S3Client $client = null) {}

    public function store(string $key, string $path): void
    {
        if (! BackupConfiguration::valid() || ! preg_match('/\Abackups\/[0-9]{4}-[0-9]{2}-[0-9]{2}\/[a-f0-9-]{36}\.dump\.gpg\z/', $key)) {
            throw new \RuntimeException('Backup destination is unavailable.');
        }
        $options = config('backup.r2');
        $client = $this->client ?? new S3Client([
            'version' => '2006-03-01', 'region' => 'auto',
            'endpoint' => 'https://'.$options['account_id'].'.r2.cloudflarestorage.com',
            'credentials' => ['key' => $options['access_key_id'], 'secret' => $options['secret_access_key']],
            'use_path_style_endpoint' => true, 'retries' => 2,
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
            'http' => ['connect_timeout' => 10, 'timeout' => 300, 'allow_redirects' => false],
        ]);
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Backup spool is unavailable.');
        }
        try {
            $size = filesize($path);
            $hash = hash_file('sha256', $path);
            // R2 privacy is bucket configuration; it does not implement S3 ACL headers.
            $upload = new MultipartUploader($client, $stream, ['bucket' => $options['bucket'], 'key' => $key, 'params' => [
                'ContentType' => 'application/octet-stream', 'Metadata' => ['sha256' => $hash],
            ], 'part_size' => max(8 * 1024 * 1024, (int) ceil($size / 9000)), 'concurrency' => 1,
                'before_upload' => function ($command) {
                    $command['ContentMD5'] = base64_encode(Utils::hash(Utils::streamFor($command['Body']), 'md5', true));
                },
            ]);
            try {
                $upload->upload();
            } catch (MultipartUploadException $exception) {
                try {
                    $client->abortMultipartUpload($exception->getState()->getId());
                } catch (\Throwable) {
                    // Independent backup-bucket lifecycle must abort orphan uploads.
                }
                throw new \RuntimeException('Backup upload failed.');
            }
            $head = $client->headObject(['Bucket' => $options['bucket'], 'Key' => $key]);
            if ((int) $head['ContentLength'] !== $size || ($head['Metadata']['sha256'] ?? null) !== $hash) {
                throw new \RuntimeException('Backup upload verification failed.');
            }
        } finally {
            fclose($stream);
        }
    }
}
