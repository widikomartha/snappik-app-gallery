<?php

namespace App\Contracts;

use App\Services\Drive\DriveItem;
use App\Services\Drive\DrivePage;
use App\Services\Drive\DriveReference;
use Psr\Http\Message\StreamInterface;

interface DriveSource
{
    public function metadata(DriveReference $reference): DriveItem;

    public function children(DriveReference $folder, ?string $pageToken = null): DrivePage;

    /** The stream must be read to EOF and closed by the caller. */
    public function original(DriveItem $expected): StreamInterface;
}
