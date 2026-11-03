<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\Api\ProjectController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0065). Read-only.
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api', 'abilities:projects:read'])->group(function (): void {
    Route::get('/projects', [ProjectController::class, 'index'])->name('api.v1.projects.index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereUuid('project')->name('api.v1.projects.show');
});
