<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('public/welcome'))->name('home');
// TASK-003 owns authentication actions. No credentials are accepted yet.
Route::get('/login', fn () => Inertia::render('auth/login'))->name('login');
Route::get('/email/verify', fn () => Inertia::render('auth/verify-email'))
    ->middleware('auth')->name('verification.notice');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('photographer/dashboard'))->name('dashboard');
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
