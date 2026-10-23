<?php

namespace App\Services\Operations;

final class BackupConfiguration
{
    public static function valid(): bool
    {
        $r2 = config('backup.r2');
        foreach (['account_id', 'access_key_id', 'secret_access_key', 'bucket'] as $key) {
            if (! is_string($r2[$key] ?? null) || $r2[$key] === '') {
                return false;
            }
        }

        return preg_match('/\A[a-f0-9]{32}\z/', $r2['account_id']) === 1
            && preg_match('/\A(?:[a-fA-F0-9]{40}|[a-fA-F0-9]{64})\z/', (string) config('backup.recipient')) === 1
            && is_string(config('backup.gpg_home')) && config('backup.gpg_home') !== ''
            // Comparing only names is intentionally conservative, even across R2 accounts.
            && $r2['bucket'] !== config('drive_proof.r2.bucket')
            && $r2['access_key_id'] !== config('drive_proof.r2.access_key_id');
    }
}
