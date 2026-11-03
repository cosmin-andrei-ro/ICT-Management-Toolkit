<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Dcim\Http\Controllers\Api\InfrastructureController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0065). Read-only.
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api', 'abilities:dcim:read'])->prefix('dcim')->group(function (): void {
    Route::get('/racks', [InfrastructureController::class, 'racks'])->name('api.v1.dcim.racks.index');
    Route::get('/racks/{rack}', [InfrastructureController::class, 'rack'])->whereUuid('rack')->name('api.v1.dcim.racks.show');
    Route::get('/power', [InfrastructureController::class, 'power'])->name('api.v1.dcim.power.index');
    Route::get('/subnets', [InfrastructureController::class, 'subnets'])->name('api.v1.dcim.subnets.index');
    Route::get('/addresses', [InfrastructureController::class, 'addresses'])->name('api.v1.dcim.addresses.index');
});
