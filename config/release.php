<?php

return [
    // Operator supplied public documents. Empty values are never advertised as legal copy.
    'terms_url' => env('SNAPPIK_TERMS_URL', ''),
    'privacy_url' => env('SNAPPIK_PRIVACY_URL', ''),
    'support_url' => env('SNAPPIK_SUPPORT_URL', ''),
    'heartbeat_seconds' => 300,
    'backup_seconds' => 26 * 3600,
];
