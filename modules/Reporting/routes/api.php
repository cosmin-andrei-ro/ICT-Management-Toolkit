<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Http\Controllers\Api\MetricController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0065). Read-only.
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api', 'abilities:metrics:read'])->group(function (): void {
    Route::get('/metrics', [MetricController::class, 'index'])->name('api.v1.metrics.index');
    Route::get('/metrics/{key}/history', [MetricController::class, 'history'])->where('key', '[a-z_]+\.[a-z_]+')->name('api.v1.metrics.history');
});
