<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\LogoutController;
use Modules\Core\Http\Livewire\Auth\Login;

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    // Without "organization": a user removed from every organization must still be able to log out.
    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'organization'])->group(function (): void {
    Route::view('/', 'core::home')->name('home');
});
