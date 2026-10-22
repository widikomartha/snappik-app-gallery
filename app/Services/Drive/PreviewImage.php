<?php

namespace App\Services\Drive;

use Psr\Http\Message\StreamInterface;

final readonly class PreviewImage
{
    public function __construct(public StreamInterface $stream, public string $mimeType, public ?int $length) {}
}
