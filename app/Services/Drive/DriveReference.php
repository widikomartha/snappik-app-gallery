<?php

namespace App\Services\Drive;

use App\Services\ProviderFailure;

final readonly class DriveReference
{
    public function __construct(public string $id, public ?string $resourceKey = null)
    {
        foreach ([$id, $resourceKey] as $value) {
            if ($value !== null && ! preg_match('/\A[A-Za-z0-9_-]{1,256}\z/', $value)) {
                throw new ProviderFailure('drive_invalid_reference');
            }
        }
    }

    public static function fromFolderUrl(#[\SensitiveParameter] string $url): self
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'drive.google.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])
            || ! preg_match('~\A/drive/(?:u/\d+/)?folders/([A-Za-z0-9_-]+)/?\z~', $parts['path'] ?? '', $match)) {
            throw new ProviderFailure('drive_invalid_folder_url');
        }
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['resourcekey']) && ! is_string($query['resourcekey'])) {
            throw new ProviderFailure('drive_invalid_reference');
        }

        return new self($match[1], $query['resourcekey'] ?? null);
    }
}
