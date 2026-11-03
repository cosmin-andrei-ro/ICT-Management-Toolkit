<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Bcm\Http\Controllers\Api\PlanController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0064).
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->prefix('continuity')->group(function (): void {
    Route::get('/plans', [PlanController::class, 'index'])->middleware('abilities:continuity:read')->name('api.v1.continuity.plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->middleware('abilities:continuity:write')->name('api.v1.continuity.plans.store');
    Route::get('/plans/{plan}', [PlanController::class, 'show'])->whereUuid('plan')->middleware('abilities:continuity:read')->name('api.v1.continuity.plans.show');
    Route::patch('/plans/{plan}', [PlanController::class, 'update'])->whereUuid('plan')->middleware('abilities:continuity:write')->name('api.v1.continuity.plans.update');
    Route::get('/exercises', [PlanController::class, 'exercises'])->middleware('abilities:continuity:read')->name('api.v1.continuity.exercises.index');
    Route::get('/activations', [PlanController::class, 'activations'])->middleware('abilities:continuity:read')->name('api.v1.continuity.activations.index');
});
