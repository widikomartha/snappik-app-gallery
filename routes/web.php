<?php

use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Client\ArchiveController;
use App\Http\Controllers\Client\DeliveryController;
use App\Http\Controllers\Client\GalleryController;
use App\Http\Controllers\Client\SelectionController;
use App\Http\Controllers\Photographer\BillingController;
use App\Http\Controllers\Photographer\ProgressController;
use App\Http\Controllers\Photographer\ProjectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('public/welcome'))->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('auth/login'))->name('login');
    Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', fn () => Inertia::render('auth/register'))->name('register');
    Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:account-mail');
    Route::get('/forgot-password', fn () => Inertia::render('auth/forgot-password'))->name('password.request');
    Route::post('/forgot-password', [AccountController::class, 'forgot'])->middleware('throttle:account-mail')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => Inertia::render('auth/reset-password', ['token' => $token, 'email' => request('email')]))->name('password.reset');
    Route::post('/reset-password', [AccountController::class, 'reset'])->middleware('throttle:account-mail')->name('password.update');
});
Route::post('/logout', [AccountController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/email/verify', fn () => Inertia::render('auth/verify-email'))
    ->middleware('auth')->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [AccountController::class, 'verify'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/email/verification-notification', [AccountController::class, 'sendVerification'])
    ->middleware(['auth', 'throttle:account-mail'])->name('verification.send');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/quote', [BillingController::class, 'quote'])->middleware('throttle:30,1');
    Route::post('/billing/orders', [BillingController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/billing/orders/{order}', [BillingController::class, 'show'])->whereUuid('order');
    Route::post('/billing/orders/{order}/resume', [BillingController::class, 'resume'])->whereUuid('order')->middleware('throttle:20,1');
    Route::get('/billing/orders/{order}/open', [BillingController::class, 'open'])->whereUuid('order');
    Route::get('/projects', [ProgressController::class, 'index'])->name('projects.index');
    Route::get('/projects/data', [ProgressController::class, 'index'])->name('projects.data');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/diagnostics', [ProjectController::class, 'diagnostics'])->name('projects.diagnostics');
    Route::get('/projects/{project}/results', [ProgressController::class, 'show'])->name('projects.results');
    Route::get('/projects/{project}/results/data', [ProgressController::class, 'show'])->name('projects.results.data');
    Route::get('/projects/{project}/results/export', [ProgressController::class, 'export'])->name('projects.results.export');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/commands', [ProjectController::class, 'command'])->name('projects.command');
    Route::post('/projects/{project}/selection/reopen', [ProjectController::class, 'reopen']);
    Route::get('/dashboard', [ProgressController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [ProgressController::class, 'index'])->name('dashboard.data');
    Route::get('/admin', fn () => Inertia::render('admin/dashboard'))
        ->middleware('can:access-admin')->name('admin.dashboard');
    Route::middleware('can:access-admin')->prefix('admin/vouchers')->name('admin.vouchers.')->group(function () {
        Route::get('/', [VoucherController::class, 'index'])->name('index');
        Route::get('/create', [VoucherController::class, 'create'])->name('create');
        Route::post('/', [VoucherController::class, 'store'])->name('store');
        Route::get('/{voucher}', [VoucherController::class, 'show'])->whereNumber('voucher')->name('show');
        Route::put('/{voucher}', [VoucherController::class, 'update'])->whereNumber('voucher')->name('update');
    });
});

Route::get('/g/{capability}', [GalleryController::class, 'show'])->name('client.gallery');
Route::get('/g/{capability}/delivery', [DeliveryController::class, 'show']);
Route::get('/g/{capability}/delivery/data', [DeliveryController::class, 'data']);
Route::get('/g/{capability}/delivery/archive', [ArchiveController::class, 'status']);
Route::post('/g/{capability}/delivery/archive', [ArchiveController::class, 'store'])->middleware('throttle:20,1');
Route::get('/g/{capability}/delivery/archive/{archive}', [ArchiveController::class, 'download'])->whereUuid('archive');
Route::get('/g/{capability}/delivery/media/{entry}', [DeliveryController::class, 'media'])->whereNumber('entry');
Route::get('/g/{capability}/selection', [SelectionController::class, 'show']);
Route::get('/g/{capability}/selection/data', [SelectionController::class, 'data']);
Route::post('/g/{capability}/selection/choices', [SelectionController::class, 'mutate']);
Route::post('/g/{capability}/selection/confirm', [SelectionController::class, 'confirm']);
Route::get('/g/{capability}/selection/preview/{entry}', [SelectionController::class, 'preview'])->whereNumber('entry');
Route::post('/g/{capability}/{type}/password', [GalleryController::class, 'unlock'])
    ->whereIn('type', ['selection', 'delivery'])->middleware('throttle:gallery-password');

// Synthetic component previews have no account/gallery data, and are local only.
Route::get('/_foundation/{surface}', function (string $surface) {
    abort_unless(app()->environment('local', 'testing'), 404);
    abort_unless(in_array($surface, ['public', 'auth', 'photographer', 'admin', 'client'], true), 404);

    return Inertia::render('public/foundation', ['surface' => $surface]);
})->name('foundation');
