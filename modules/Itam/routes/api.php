<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Itam\Http\Controllers\Api\AssetCategoryController;
use Modules\Itam\Http\Controllers\Api\AssetController;
use Modules\Itam\Http\Controllers\Api\LicenseController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0062).
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('/assets/categories', [AssetCategoryController::class, 'index'])->middleware('abilities:assets:read')->name('api.v1.assets.categories.index');
    Route::get('/assets', [AssetController::class, 'index'])->middleware('abilities:assets:read')->name('api.v1.assets.index');
    Route::post('/assets', [AssetController::class, 'store'])->middleware('abilities:assets:write')->name('api.v1.assets.store');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->whereUuid('asset')->middleware('abilities:assets:read')->name('api.v1.assets.show');
    Route::patch('/assets/{asset}', [AssetController::class, 'update'])->whereUuid('asset')->middleware('abilities:assets:write')->name('api.v1.assets.update');
    Route::post('/assets/{asset}/assign', [AssetController::class, 'assign'])->whereUuid('asset')->middleware('abilities:assets:write')->name('api.v1.assets.assign');
    Route::post('/assets/{asset}/status', [AssetController::class, 'status'])->whereUuid('asset')->middleware('abilities:assets:write')->name('api.v1.assets.status');

    Route::get('/licenses', [LicenseController::class, 'index'])->middleware('abilities:assets:read')->name('api.v1.licenses.index');
    Route::post('/licenses', [LicenseController::class, 'store'])->middleware('abilities:assets:write')->name('api.v1.licenses.store');
    Route::get('/licenses/{license}', [LicenseController::class, 'show'])->whereUuid('license')->middleware('abilities:assets:read')->name('api.v1.licenses.show');
    Route::patch('/licenses/{license}', [LicenseController::class, 'update'])->whereUuid('license')->middleware('abilities:assets:write')->name('api.v1.licenses.update');
    Route::post('/licenses/{license}/seats', [LicenseController::class, 'addSeat'])->whereUuid('license')->middleware('abilities:assets:write')->name('api.v1.licenses.seats.store');
    Route::delete('/licenses/{license}/seats/{seat}', [LicenseController::class, 'removeSeat'])->whereUuid(['license', 'seat'])->middleware('abilities:assets:write')->name('api.v1.licenses.seats.destroy');
});
