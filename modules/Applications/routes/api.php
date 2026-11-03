<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Applications\Http\Controllers\Api\ApplicationController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0064).
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('/applications', [ApplicationController::class, 'index'])->middleware('abilities:applications:read')->name('api.v1.applications.index');
    Route::post('/applications', [ApplicationController::class, 'store'])->middleware('abilities:applications:write')->name('api.v1.applications.store');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->whereUuid('application')->middleware('abilities:applications:read')->name('api.v1.applications.show');
    Route::patch('/applications/{application}', [ApplicationController::class, 'update'])->whereUuid('application')->middleware('abilities:applications:write')->name('api.v1.applications.update');
    Route::get('/applications/{application}/sheet', [ApplicationController::class, 'sheet'])->whereUuid('application')->middleware('abilities:applications:read')->name('api.v1.applications.sheet');
});
