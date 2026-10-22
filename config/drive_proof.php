<?php

return [
    'api_key' => env('GOOGLE_DRIVE_API_KEY', ''),
    // Test source capability stays in the ignored environment, never command arguments.
    'folder_url' => env('DRIVE_PROOF_FOLDER_URL', ''),
    'r2' => [
        'account_id' => env('R2_ACCOUNT_ID', ''),
        'access_key_id' => env('R2_ACCESS_KEY_ID', ''),
        'secret_access_key' => env('R2_SECRET_ACCESS_KEY', ''),
        'bucket' => env('R2_ARCHIVE_BUCKET', ''),
    ],
];
