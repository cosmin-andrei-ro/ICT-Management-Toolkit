<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Bia\Http\Controllers\Api\ProcessController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0064).
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->prefix('bia')->group(function (): void {
    Route::get('/processes', [ProcessController::class, 'index'])->middleware('abilities:bia:read')->name('api.v1.bia.processes.index');
    Route::post('/processes', [ProcessController::class, 'store'])->middleware('abilities:bia:write')->name('api.v1.bia.processes.store');
    Route::get('/processes/{process}', [ProcessController::class, 'show'])->whereUuid('process')->middleware('abilities:bia:read')->name('api.v1.bia.processes.show');
    Route::patch('/processes/{process}', [ProcessController::class, 'update'])->whereUuid('process')->middleware('abilities:bia:write')->name('api.v1.bia.processes.update');
});
