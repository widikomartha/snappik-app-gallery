<?php

use App\Http\Controllers\Auth\AccountController;
use App\Services\Access\AccountPolicy;
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
    Route::get('/dashboard', function (AccountPolicy $policy) {
        abort_unless(request()->user()->role === 'photographer', 403);

        return Inertia::render('photographer/dashboard', ['accountState' => $policy->state(request()->user())]);
    })->name('dashboard');
    Route::get('/admin', fn () => Inertia::render('admin/dashboard'))
        ->middleware('can:access-admin')->name('admin.dashboard');
});

// TASK-004 must resolve and authorize capabilities before rendering client data.
Route::get('/g/{capability}', fn () => abort(404))->name('client.gallery');

// Synthetic component previews have no account/gallery data, and are local only.
Route::get('/_foundation/{surface}', function (string $surface) {
    abort_unless(app()->environment('local', 'testing'), 404);
    abort_unless(in_array($surface, ['public', 'auth', 'photographer', 'admin', 'client'], true), 404);

    return Inertia::render('public/foundation', ['surface' => $surface]);
})->name('foundation');
