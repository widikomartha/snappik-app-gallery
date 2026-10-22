<?php

namespace App\Contracts;

use App\Services\Drive\ByteRange;
use App\Services\Drive\DriveItem;
use Psr\Http\Message\StreamInterface;

interface DriveRangeSource
{
    /** Verified partial original; caller must read to EOF and close. */
    public function originalRange(DriveItem $expected, ByteRange $range): StreamInterface;
}
