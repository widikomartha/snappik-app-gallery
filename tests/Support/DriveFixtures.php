<?php

namespace Tests\Support;

use App\Services\Drive\DriveItem;
use GuzzleHttp\Psr7\Response;

final class DriveFixtures
{
    public static function folder(string $id = 'root', ?string $key = null): array
    {
        return ['id' => $id, 'name' => $id, 'mimeType' => DriveItem::FOLDER, 'resourceKey' => $key];
    }

    public static function photo(string $id = 'photo1', string $bytes = 'original-one', string $name = 'same.jpg'): array
    {
        return ['id' => $id, 'name' => $name, 'mimeType' => 'image/jpeg', 'size' => (string) strlen($bytes),
            'version' => '7', 'md5Checksum' => md5($bytes), 'modifiedTime' => '2026-10-22T00:00:00Z',
            'capabilities' => ['canDownload' => true]];
    }

    public static function json(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }
}
