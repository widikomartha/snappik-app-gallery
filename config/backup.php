<?php

return [
    'enabled' => (bool) env('SNAPPIK_BACKUP_ENABLED', false),
    // Linux Bash with pipefail; public encryption key only on the application host.
    'bash' => env('SNAPPIK_BACKUP_BASH', '/bin/bash'),
    'pg_dump' => env('SNAPPIK_PG_DUMP', '/usr/bin/pg_dump'),
    'gpg' => env('SNAPPIK_GPG', '/usr/bin/gpg'),
    'gpg_home' => env('SNAPPIK_BACKUP_GPG_HOME', ''),
    'recipient' => env('SNAPPIK_BACKUP_RECIPIENT', ''),
    'r2' => [
        'account_id' => env('R2_BACKUP_ACCOUNT_ID', ''),
        'access_key_id' => env('R2_BACKUP_ACCESS_KEY_ID', ''),
        'secret_access_key' => env('R2_BACKUP_SECRET_ACCESS_KEY', ''),
        'bucket' => env('R2_BACKUP_BUCKET', ''),
    ],
];
