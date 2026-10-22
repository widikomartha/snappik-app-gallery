<?php

return [
    // Sandbox only until a separately authorized production integration/release.
    'merchant_id' => env('MIDTRANS_SANDBOX_MERCHANT_ID', ''),
    'server_key' => env('MIDTRANS_SANDBOX_SERVER_KEY', ''),
    'qris_minimum' => (int) env('MIDTRANS_QRIS_MINIMUM_IDR', 1),
];
