<?php

use App\Http\Controllers\Webhooks\MidtransController;
use Illuminate\Support\Facades\Route;

// Intentionally outside session/CSRF middleware; the adapter verifies the provider signature.
Route::post('/webhooks/midtrans', MidtransController::class)->middleware('throttle:120,1')->name('webhooks.midtrans');
