<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cmdb\Http\Controllers\Api\CiTypeController;
use Modules\Cmdb\Http\Controllers\Api\ConfigurationItemController;
use Modules\Cmdb\Http\Controllers\Api\RelationshipController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0062).
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->prefix('cmdb')->group(function (): void {
    Route::get('/types', [CiTypeController::class, 'index'])->middleware('abilities:cmdb:read')->name('api.v1.cmdb.types.index');
    Route::get('/items', [ConfigurationItemController::class, 'index'])->middleware('abilities:cmdb:read')->name('api.v1.cmdb.items.index');
    Route::post('/items', [ConfigurationItemController::class, 'store'])->middleware('abilities:cmdb:write')->name('api.v1.cmdb.items.store');
    Route::get('/items/{item}', [ConfigurationItemController::class, 'show'])->whereUuid('item')->middleware('abilities:cmdb:read')->name('api.v1.cmdb.items.show');
    Route::patch('/items/{item}', [ConfigurationItemController::class, 'update'])->whereUuid('item')->middleware('abilities:cmdb:write')->name('api.v1.cmdb.items.update');
    Route::delete('/items/{item}', [ConfigurationItemController::class, 'destroy'])->whereUuid('item')->middleware('abilities:cmdb:write')->name('api.v1.cmdb.items.destroy');
    Route::post('/relationships', [RelationshipController::class, 'store'])->middleware('abilities:cmdb:write')->name('api.v1.cmdb.relationships.store');
    Route::delete('/relationships/{relationship}', [RelationshipController::class, 'destroy'])->whereUuid('relationship')->middleware('abilities:cmdb:write')->name('api.v1.cmdb.relationships.destroy');
});
