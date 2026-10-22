<?php

namespace App\Contracts;

interface ArchiveUpload
{
    public function write(string $bytes): int;

    /** Finalize and verify the private object's length before returning. */
    public function complete(): int;

    /** Also deletes an object if completion succeeded but verification failed. */
    public function abort(): void;
}
