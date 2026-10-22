<?php

namespace App\Services\Drive;

use App\Services\ProviderFailure;

final readonly class DriveItem
{
    public const FOLDER = 'application/vnd.google-apps.folder';

    public const SHORTCUT = 'application/vnd.google-apps.shortcut';

    public function __construct(
        public DriveReference $reference,
        public string $name,
        public string $mimeType,
        public int $size = 0,
        public string $version = '',
        public ?string $md5 = null,
        public bool $canDownload = false,
        public ?string $modifiedTime = null,
    ) {}

    public function isPhoto(): bool
    {
        return in_array($this->mimeType, ['image/jpeg', 'image/png'], true);
    }

    public function snapshot(): array
    {
        return ['id' => $this->reference->id, 'resourceKey' => $this->reference->resourceKey, 'name' => $this->name,
            'mimeType' => $this->mimeType, 'size' => (string) $this->size, 'version' => $this->version,
            'md5Checksum' => $this->md5, 'capabilities' => ['canDownload' => $this->canDownload], 'modifiedTime' => $this->modifiedTime];
    }

    public static function fromArray(array $data): self
    {
        if (! is_string($data['id'] ?? null) || ! is_string($data['name'] ?? null) || ! is_string($data['mimeType'] ?? null)
            || ($data['trashed'] ?? false)) {
            throw new ProviderFailure('drive_invalid_metadata');
        }
        $photo = in_array($data['mimeType'], ['image/jpeg', 'image/png'], true);
        $size = filter_var($data['size'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($size === false || ($photo && (! isset($data['size'], $data['version']) || (string) $data['version'] === ''))
            || (isset($data['md5Checksum']) && ! preg_match('/\A[a-f0-9]{32}\z/i', $data['md5Checksum']))) {
            throw new ProviderFailure('drive_invalid_metadata');
        }

        return new self(new DriveReference($data['id'], $data['resourceKey'] ?? null), $data['name'], $data['mimeType'],
            $size, (string) ($data['version'] ?? ''), $data['md5Checksum'] ?? null,
            ($data['capabilities']['canDownload'] ?? false) === true, $data['modifiedTime'] ?? null);
    }
}
