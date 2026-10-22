<?php

namespace App\Integrations\GoogleDrive;

use App\Contracts\DriveSource;
use App\Services\Drive\DriveItem;
use App\Services\Drive\DrivePage;
use App\Services\Drive\DriveReference;
use App\Services\ProviderFailure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class PublicDriveSource implements DriveSource
{
    private const FIELDS = 'id,name,mimeType,size,version,md5Checksum,modifiedTime,resourceKey,trashed,capabilities(canDownload)';

    public function __construct(private ClientInterface $http, #[\SensitiveParameter] private string $apiKey, private int $attempts = 3, private ?\Closure $sleep = null, private ?\Closure $beforeRequest = null) {}

    public function metadata(DriveReference $reference): DriveItem
    {
        $data = $this->json($this->request($reference, 'files/'.$reference->id, ['fields' => self::FIELDS]));
        $item = DriveItem::fromArray($data);
        if ($item->reference->id !== $reference->id) {
            throw new ProviderFailure('drive_identity_mismatch');
        }

        return $item;
    }

    public function children(DriveReference $folder, ?string $pageToken = null): DrivePage
    {
        $query = ['q' => "'{$folder->id}' in parents and trashed = false", 'pageSize' => 1000,
            'fields' => 'nextPageToken,incompleteSearch,files('.self::FIELDS.')'];
        if ($pageToken !== null) {
            $query['pageToken'] = $pageToken;
        }
        $data = $this->json($this->request($folder, 'files', $query));
        if (($data['incompleteSearch'] ?? false) || ! is_array($data['files'] ?? null)
            || (isset($data['nextPageToken']) && (! is_string($data['nextPageToken']) || $data['nextPageToken'] === ''))) {
            throw new ProviderFailure('drive_incomplete_listing');
        }

        return new DrivePage(array_map(DriveItem::fromArray(...), $data['files']), $data['nextPageToken'] ?? null);
    }

    public function original(DriveItem $expected): StreamInterface
    {
        $current = $this->metadata($expected->reference);
        if (! $current->isPhoto() || ! $current->canDownload) {
            throw new ProviderFailure('drive_download_restricted');
        }
        if ($current->size !== $expected->size || $current->version !== $expected->version || $current->md5 !== $expected->md5
            || $current->mimeType !== $expected->mimeType || $current->modifiedTime !== $expected->modifiedTime) {
            throw new ProviderFailure('drive_source_changed');
        }
        $response = $this->request($expected->reference, 'files/'.$expected->reference->id, ['alt' => 'media'], true);
        $stream = $response->getBody();
        $type = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
        $length = $response->getHeaderLine('Content-Length');
        if (! in_array($type, [$expected->mimeType, 'application/octet-stream'], true)
            || ($length !== '' && $length !== (string) $expected->size)) {
            $stream->close();
            throw new ProviderFailure('drive_invalid_media_response');
        }

        return new VerifiedOriginalStream($stream, $expected, function () use ($expected): void {
            // Detect a metadata change while the original was in flight.
            $after = $this->metadata($expected->reference);
            if ($after->version !== $expected->version || $after->size !== $expected->size || $after->md5 !== $expected->md5) {
                throw new ProviderFailure('drive_source_changed');
            }
        });
    }

    private function json(ResponseInterface $response): array
    {
        try {
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ProviderFailure('drive_invalid_api_response');
        } finally {
            $response->getBody()->close();
        }
        if (! is_array($data)) {
            throw new ProviderFailure('drive_invalid_api_response');
        }

        return $data;
    }

    private function request(DriveReference $reference, string $path, array $query, bool $stream = false): ResponseInterface
    {
        if ($this->apiKey === '') {
            throw new ProviderFailure('drive_credentials_missing');
        }
        // Drive files.get advertises useMediaDownloadService=true. Google's
        // Discovery contract requires /download before servicePath for media;
        // metadata/listing stay on the normal API path and redirects stay off.
        $base = ($query['alt'] ?? null) === 'media'
            ? 'https://www.googleapis.com/download/drive/v3/'
            : 'https://www.googleapis.com/drive/v3/';
        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            try {
                $this->beforeRequest?->__invoke();
                $response = $this->http->request('GET', $base.$path, [
                    'query' => $query, 'headers' => array_filter(['X-Goog-Api-Key' => $this->apiKey,
                        'X-Goog-Drive-Resource-Keys' => $reference->resourceKey === null ? null : $reference->id.'/'.$reference->resourceKey]),
                    'stream' => $stream, 'http_errors' => false, 'allow_redirects' => false,
                    'connect_timeout' => 10, 'timeout' => $stream ? 0 : 60, 'read_timeout' => 60,
                ]);
                $status = $response->getStatusCode();
                if ($status === 200) {
                    return $response;
                }
                $reason = null;
                $automatedRequestsBlocked = false;
                $contentType = strtolower($response->getHeaderLine('Content-Type'));
                try {
                    // Error responses may themselves be streamed. Inspect only a bounded
                    // diagnostic prefix; never log it or use HTML as a media fallback.
                    $errorBody = $response->getBody()->read(16384);
                    if (str_contains($contentType, 'json')) {
                        $payload = json_decode($errorBody, true);
                        $reason = $payload['error']['errors'][0]['reason'] ?? null;
                    }
                    $automatedRequestsBlocked = $status === 403 && str_contains($contentType, 'text/html')
                        && str_contains(strtolower($errorBody), 'automated queries');
                } catch (\Throwable) {
                    // Preserve the native status when the diagnostic body cannot be read.
                } finally {
                    $response->getBody()->close();
                }
                $retryable = $status === 429 || $status >= 500 || ($status === 403 && in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded'], true));
                $failure = new ProviderFailure(match (true) {
                    $automatedRequestsBlocked => 'drive_automated_requests_blocked',
                    $status === 403 && $reason === 'downloadQuotaExceeded' => 'drive_download_quota_exceeded',
                    $retryable => 'drive_temporarily_unavailable',
                    $status === 403 => 'drive_access_denied',
                    $status === 404 => 'drive_not_found_or_resource_key_required',
                    $status >= 300 && $status < 400 => 'drive_redirect_rejected',
                    default => 'drive_request_rejected',
                }, $status, $retryable);
            } catch (GuzzleException) {
                $failure = new ProviderFailure('drive_connection_failed', retryable: true);
            }
            if (! $failure->retryable || $attempt === $this->attempts) {
                throw $failure;
            }
            ($this->sleep ?? fn (int $ms) => usleep($ms * 1000))(min(4000, 250 * 2 ** ($attempt - 1)) + random_int(0, 200));
        }
        throw new ProviderFailure('drive_request_failed');
    }
}
