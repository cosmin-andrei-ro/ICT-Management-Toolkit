<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Risk\Http\Controllers\Api\CatalogController;
use Modules\Risk\Http\Controllers\Api\RiskController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0063).
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->prefix('risks')->group(function (): void {
    Route::get('/catalog', [CatalogController::class, 'index'])->middleware('abilities:risks:read')->name('api.v1.risks.catalog');
    Route::get('/', [RiskController::class, 'index'])->middleware('abilities:risks:read')->name('api.v1.risks.index');
    Route::post('/', [RiskController::class, 'store'])->middleware('abilities:risks:write')->name('api.v1.risks.store');
    Route::get('/{risk}', [RiskController::class, 'show'])->whereUuid('risk')->middleware('abilities:risks:read')->name('api.v1.risks.show');
    Route::patch('/{risk}', [RiskController::class, 'update'])->whereUuid('risk')->middleware('abilities:risks:write')->name('api.v1.risks.update');
    Route::put('/{risk}/assessment', [RiskController::class, 'assess'])->whereUuid('risk')->middleware('abilities:risks:write')->name('api.v1.risks.assessment');
    Route::put('/{risk}/treatment', [RiskController::class, 'treat'])->whereUuid('risk')->middleware('abilities:risks:write')->name('api.v1.risks.treatment');
    Route::post('/{risk}/assets', [RiskController::class, 'addAsset'])->whereUuid('risk')->middleware('abilities:risks:write')->name('api.v1.risks.assets.store');
    Route::delete('/{risk}/assets/{type}/{asset}', [RiskController::class, 'removeAsset'])->whereUuid(['risk', 'asset'])->middleware('abilities:risks:write')->name('api.v1.risks.assets.destroy');
    Route::post('/{risk}/actions', [RiskController::class, 'addAction'])->whereUuid('risk')->middleware('abilities:risks:write')->name('api.v1.risks.actions.store');
    Route::patch('/{risk}/actions/{action}', [RiskController::class, 'updateAction'])->whereUuid(['risk', 'action'])->middleware('abilities:risks:write')->name('api.v1.risks.actions.update');
});
