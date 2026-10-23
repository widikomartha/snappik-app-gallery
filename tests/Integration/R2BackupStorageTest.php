<?php

namespace Tests\Integration;

use App\Integrations\ArchiveStorage\R2BackupStorage;
use Aws\S3\S3Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

class R2BackupStorageTest extends TestCase
{
    public function test_sdk_upload_has_no_acl_header_uses_exact_backup_bucket_verifies_metadata_and_aborts_failed_parts(): void
    {
        config(['backup.r2' => ['account_id' => str_repeat('a', 32), 'access_key_id' => 'synthetic-backup-only', 'secret_access_key' => 'synthetic', 'bucket' => 'synthetic-backups'],
            'backup.recipient' => str_repeat('A', 40), 'backup.gpg_home' => '/public-key-only',
            'drive_proof.r2.bucket' => 'synthetic-archives', 'drive_proof.r2.access_key_id' => 'synthetic-archive-only']);
        $path = tempnam(sys_get_temp_dir(), 'backup-sdk-');
        file_put_contents($path, str_repeat('encrypted fixture', 1024));
        try {
            foreach (['success', 'part_failure', 'bad_head'] as $case) {
                $calls = [];
                $uploaded = '';
                $metadata = '';
                $client = new S3Client(['version' => '2006-03-01', 'region' => 'auto', 'endpoint' => 'https://synthetic.test',
                    'use_path_style_endpoint' => true, 'credentials' => ['key' => 'synthetic', 'secret' => 'synthetic'], 'retries' => 0,
                    'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
                    'http_handler' => function ($request) use ($case, &$calls, &$uploaded, &$metadata) {
                        $this->assertFalse($request->hasHeader('x-amz-acl'));
                        $this->assertStringStartsWith('/synthetic-backups/backups/', $request->getUri()->getPath());
                        $method = $request->getMethod();
                        $calls[] = $method;
                        if ($method === 'POST' && str_contains($request->getUri()->getQuery(), 'uploads')) {
                            $metadata = $request->getHeaderLine('x-amz-meta-sha256');

                            return Create::promiseFor(new Response(200, [], '<InitiateMultipartUploadResult><UploadId>synthetic</UploadId></InitiateMultipartUploadResult>'));
                        }
                        if ($method === 'PUT') {
                            $uploaded = (string) $request->getBody();
                            $this->assertSame(base64_encode(md5($uploaded, true)), $request->getHeaderLine('Content-MD5'));

                            return $case === 'part_failure'
                                ? Create::rejectionFor(['exception' => new RequestException('Synthetic failure', $request),
                                    'response' => new Response(503, [], '<Error><Code>ServiceUnavailable</Code></Error>')])
                                : Create::promiseFor(new Response(200, ['ETag' => '"synthetic-part"']));
                        }
                        if ($method === 'HEAD') {
                            return Create::promiseFor(new Response(200, ['Content-Length' => (string) strlen($uploaded), 'x-amz-meta-sha256' => $case === 'bad_head' ? 'wrong' : $metadata]));
                        }

                        return Create::promiseFor(new Response(200, [], $method === 'POST' ? '<CompleteMultipartUploadResult><ETag>"synthetic"</ETag></CompleteMultipartUploadResult>' : ''));
                    },
                ]);
                try {
                    (new R2BackupStorage($client))->store('backups/2026-10-23/11111111-1111-1111-1111-111111111111.dump.gpg', $path);
                    $this->assertSame('success', $case);
                    $this->assertSame(file_get_contents($path), $uploaded);
                    $this->assertSame(hash_file('sha256', $path), $metadata);
                } catch (\RuntimeException $exception) {
                    if ($exception instanceof AssertionFailedError) {
                        throw $exception;
                    }
                    if ($case === 'success') {
                        throw $exception;
                    }
                    $this->assertSame($case === 'part_failure' ? 'Backup upload failed.' : 'Backup upload verification failed.', $exception->getMessage());
                }
                if ($case === 'part_failure') {
                    $this->assertSame('POST', $calls[0]);
                    $this->assertSame('DELETE', $calls[array_key_last($calls)]);
                    $this->assertSame(['PUT'], array_values(array_unique(array_slice($calls, 1, -1))));
                } else {
                    $this->assertSame(['POST', 'PUT', 'POST', 'HEAD'], $calls);
                }
            }
        } finally {
            unlink($path);
        }
    }
}
