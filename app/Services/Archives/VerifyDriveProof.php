<?php

namespace App\Services\Archives;

use App\Contracts\ArchiveStorage;
use App\Contracts\DriveSource;
use App\Models\DriveProofRun;
use App\Services\Drive\DriveItem;
use App\Services\ProviderFailure;
use ZipArchive;

final class VerifyDriveProof
{
    public function __construct(private ArchiveStorage $storage, private DriveSource $drive) {}

    // Diagnostic smoke test only. Uses temporary disk to independently extract the
    // completed R2 object, not the worker's buffers or a second generated ZIP.
    public function verify(DriveProofRun $run, int $ownerId): array
    {
        abort_unless(app()->environment('local', 'testing') && (int) $run->user_id === $ownerId, 403);
        if ($run->state !== 'ready' || $run->expires_at === null || $run->expires_at->isPast()) {
            throw new ProviderFailure('archive_not_ready_or_expired');
        }
        $path = tempnam(sys_get_temp_dir(), 'snappik-verify-');
        if ($path === false) {
            throw new ProviderFailure('archive_temporary_disk_unavailable');
        }
        $zip = new ZipArchive;
        $opened = false;
        try {
            $body = $this->storage->read($run->object_key);
            $file = fopen($path, 'wb');
            $downloaded = 0;
            try {
                while (! $body->eof()) {
                    $chunk = $body->read(1024 ** 2);
                    if ($chunk === '' && ! $body->eof()) {
                        throw new ProviderFailure('archive_read_stalled');
                    }
                    $downloaded += strlen($chunk);
                    if (fwrite($file, $chunk) !== strlen($chunk)) {
                        throw new ProviderFailure('archive_temporary_disk_write_failed');
                    }
                }
            } finally {
                $body->close();
                fclose($file);
            }
            if ($downloaded !== (int) $run->zip_bytes || $zip->open($path) !== true) {
                throw new ProviderFailure('archive_completed_zip_invalid');
            }
            $opened = true;
            $photos = $run->nodes()->where('kind', 'photo');
            if ($zip->numFiles !== (clone $photos)->count()) {
                throw new ProviderFailure('archive_manifest_mismatch');
            }
            $count = 0;
            $total = 0;
            foreach ($photos->orderBy('path')->orderBy('file_id')->lazy(200) as $node) {
                $item = DriveItem::fromArray($node->metadata);
                $stat = $zip->statName($node->path);
                $entry = $zip->getStream($node->path);
                if ($stat === false || $stat['size'] !== $item->size || $entry === false) {
                    throw new ProviderFailure('archive_manifest_mismatch');
                }
                $hash = hash_init('md5');
                $crc = hash_init('crc32b');
                $bytes = 0;
                try {
                    while (! feof($entry)) {
                        $chunk = fread($entry, 1024 ** 2);
                        if ($chunk === false) {
                            throw new ProviderFailure('archive_crc_failed');
                        }
                        $bytes += strlen($chunk);
                        hash_update($hash, $chunk);
                        hash_update($crc, $chunk);
                    }
                } finally {
                    fclose($entry);
                }
                $expectedMd5 = $item->md5;
                if ($expectedMd5 === null) {
                    $original = $this->drive->original($item);
                    $sourceHash = hash_init('md5');
                    try {
                        while (! $original->eof()) {
                            hash_update($sourceHash, $original->read(1024 ** 2));
                        }
                    } finally {
                        $original->close();
                    }
                    $expectedMd5 = hash_final($sourceHash);
                }
                if ($bytes !== $item->size || ! hash_equals(strtolower($expectedMd5), hash_final($hash))
                    || hash_final($crc) !== sprintf('%08x', $stat['crc'])) {
                    throw new ProviderFailure('archive_crc_or_original_mismatch');
                }
                $count++;
                $total += $bytes;
            }
            $run->update(['verified_at' => now()]);

            return ['verified_files' => $count, 'verified_source_bytes' => $total, 'verified_zip_bytes' => $downloaded, 'crc_and_original_md5' => 'passed'];
        } catch (\Throwable $error) {
            throw $error instanceof ProviderFailure ? $error : new ProviderFailure('archive_verification_failed');
        } finally {
            if ($opened) {
                $zip->close();
            }
            unlink($path);
        }
    }
}
