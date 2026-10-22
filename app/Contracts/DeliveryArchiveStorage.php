<?php

namespace App\Contracts;

use App\Services\Drive\ByteRange;
use Psr\Http\Message\StreamInterface;

interface DeliveryArchiveStorage extends ArchiveStorage
{
    public function slice(string $key, ByteRange $range, int $expectedSize): StreamInterface;

    /** Abort every multipart upload for this exact attempt key, then delete its object. */
    public function cleanup(string $key): void;
}
