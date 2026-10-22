<?php

namespace App\Services\Archives;

use App\Services\ProviderFailure;

final class ArchivePath
{
    public static function component(string $name): string
    {
        $safe = preg_replace('/[\x00-\x1F\x7F\/\\\\:<>"|?*]/u', '_', $name);
        $safe = trim($safe ?? '', ". \t\n\r\0\x0B");
        $safe = preg_replace('/\.{2,}/', '_', $safe);
        if ($safe === '') {
            $safe = 'unnamed';
        }

        return mb_strcut($safe, 0, 160, 'UTF-8');
    }

    // Suffix every component with the provider identity (hashed if very long). This avoids
    // collision with a real filename resembling a generated duplicate suffix.
    public static function append(string $parent, string $name, string $id, bool $photo): string
    {
        $name = self::component($name);
        $extension = $photo ? pathinfo($name, PATHINFO_EXTENSION) : '';
        $stem = $extension === '' ? $name : substr($name, 0, -strlen($extension) - 1);
        $suffix = strlen($id) > 64 ? hash('sha256', $id) : $id;
        $component = $stem.'__'.$suffix.($extension === '' ? '' : '.'.$extension);
        $path = ($parent === '' ? '' : $parent.'/').$component;
        if (strlen($path) > 60000) {
            throw new ProviderFailure('zip_native_path_capacity_exceeded');
        }

        return $path;
    }
}
