<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\Api\ControlController;
use Modules\Audit\Http\Controllers\Api\FindingController;
use Modules\Audit\Http\Controllers\Api\FrameworkController;

// Mounted under /api/v1 by the module provider (ADR 0009, 0061, 0063).
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->prefix('compliance')->group(function (): void {
    Route::middleware('abilities:compliance:read')->group(function (): void {
        Route::get('/frameworks', [FrameworkController::class, 'index'])->name('api.v1.compliance.frameworks.index');
        Route::get('/frameworks/{framework}/requirements', [FrameworkController::class, 'requirements'])->whereUuid('framework')->name('api.v1.compliance.frameworks.requirements');
        Route::get('/audits', [FrameworkController::class, 'audits'])->name('api.v1.compliance.audits.index');
        Route::get('/controls', [ControlController::class, 'index'])->name('api.v1.compliance.controls.index');
        Route::get('/controls/{control}', [ControlController::class, 'show'])->whereUuid('control')->name('api.v1.compliance.controls.show');
        Route::get('/findings', [FindingController::class, 'index'])->name('api.v1.compliance.findings.index');
        Route::get('/findings/{finding}', [FindingController::class, 'show'])->whereUuid('finding')->name('api.v1.compliance.findings.show');
    });

    Route::middleware('abilities:compliance:write')->group(function (): void {
        Route::post('/controls', [ControlController::class, 'store'])->name('api.v1.compliance.controls.store');
        Route::patch('/controls/{control}', [ControlController::class, 'update'])->whereUuid('control')->name('api.v1.compliance.controls.update');
        Route::put('/controls/{control}/implementation', [ControlController::class, 'implementation'])->whereUuid('control')->name('api.v1.compliance.controls.implementation');
        Route::post('/controls/{control}/requirements', [ControlController::class, 'mapRequirement'])->whereUuid('control')->name('api.v1.compliance.controls.requirements.store');
        Route::delete('/controls/{control}/requirements/{requirement}', [ControlController::class, 'unmapRequirement'])->whereUuid(['control', 'requirement'])->name('api.v1.compliance.controls.requirements.destroy');
        Route::post('/controls/{control}/evidence', [ControlController::class, 'linkEvidence'])->whereUuid('control')->name('api.v1.compliance.controls.evidence.store');
        Route::delete('/controls/{control}/evidence/{evidence}', [ControlController::class, 'removeEvidence'])->whereUuid(['control', 'evidence'])->name('api.v1.compliance.controls.evidence.destroy');
        Route::post('/controls/{control}/tests', [ControlController::class, 'recordTest'])->whereUuid('control')->name('api.v1.compliance.controls.tests.store');
        Route::post('/findings', [FindingController::class, 'store'])->name('api.v1.compliance.findings.store');
        Route::patch('/findings/{finding}', [FindingController::class, 'update'])->whereUuid('finding')->name('api.v1.compliance.findings.update');
        Route::post('/findings/{finding}/actions', [FindingController::class, 'addAction'])->whereUuid('finding')->name('api.v1.compliance.findings.actions.store');
        Route::patch('/findings/{finding}/actions/{action}', [FindingController::class, 'updateAction'])->whereUuid(['finding', 'action'])->name('api.v1.compliance.findings.actions.update');
    });
});
