<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Documentation\Http\Controllers\Api\DocumentationController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0065). Read-only.
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api', 'abilities:documentation:read'])->prefix('documentation')->group(function (): void {
    Route::get('/spaces', [DocumentationController::class, 'spaces'])->name('api.v1.documentation.spaces.index');
    Route::get('/pages', [DocumentationController::class, 'pages'])->name('api.v1.documentation.pages.index');
    Route::get('/pages/{page}', [DocumentationController::class, 'page'])->whereUuid('page')->name('api.v1.documentation.pages.show');
});
