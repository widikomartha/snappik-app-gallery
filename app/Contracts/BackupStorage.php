<?php

namespace App\Contracts;

interface BackupStorage
{
    /** Upload and verify an encrypted local spool; never store plaintext or use the archive bucket. */
    public function store(string $key, string $path): void;
}
