<?php

namespace App\Contracts;

use Psr\Http\Message\StreamInterface;

interface ArchiveStorage
{
    public function begin(string $key, int $maximumBytes): ArchiveUpload;

    public function delete(string $key): void;

    public function read(string $key): StreamInterface;
}
