<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\DirectoryController;
use Modules\Core\Http\Controllers\Api\SupplierController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0065). Read-only.
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::middleware('abilities:directory:read')->prefix('directory')->group(function (): void {
        Route::get('/people', [DirectoryController::class, 'people'])->name('api.v1.directory.people');
        Route::get('/teams', [DirectoryController::class, 'teams'])->name('api.v1.directory.teams');
        Route::get('/departments', [DirectoryController::class, 'departments'])->name('api.v1.directory.departments');
        Route::get('/locations', [DirectoryController::class, 'locations'])->name('api.v1.directory.locations');
    });

    Route::middleware('abilities:suppliers:read')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('api.v1.suppliers.index');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->whereUuid('supplier')->name('api.v1.suppliers.show');
        Route::get('/contracts', [SupplierController::class, 'contracts'])->name('api.v1.contracts.index');
    });
});
