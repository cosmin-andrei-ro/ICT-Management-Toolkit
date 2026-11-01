<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AttachmentController;
use Modules\Core\Http\Controllers\ExportController;
use Modules\Core\Http\Controllers\LogoutController;
use Modules\Core\Http\Controllers\ManualController;
use Modules\Core\Http\Livewire\Auth\Login;

// Public: needed most by people who cannot sign in.
Route::get('/manual', [ManualController::class, 'index'])->name('manual.index');
Route::get('/manual/{page}', [ManualController::class, 'show'])->where('page', '[a-z0-9-]+')->name('manual.show');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    // Without "organization": a user removed from every organization must still be able to log out.
    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'organization'])->group(function (): void {
    Route::view('/', 'core::home')->name('home');
    Route::get('/attachments/{attachment}', AttachmentController::class)->name('core.attachments.download');
    Route::get('/exports/{export}', ExportController::class)->whereUuid('export')->name('core.exports.download');
});
