<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Itam\Http\Controllers\AssetLabelsController;
use Modules\Itam\Http\Controllers\HandoverProtocolController;
use Modules\Itam\Http\Livewire\AssetForm;
use Modules\Itam\Http\Livewire\AssetIndex;
use Modules\Itam\Http\Livewire\ImportAssets;
use Modules\Itam\Http\Livewire\MyEquipment;
use Modules\Itam\Http\Livewire\ShowAsset;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/assets', AssetIndex::class)->name('itam.assets.index');
    Route::livewire('/assets/new', AssetForm::class)->name('itam.assets.create');
    Route::livewire('/assets/import', ImportAssets::class)->name('itam.assets.import');
    Route::get('/assets/labels', AssetLabelsController::class)->name('itam.assets.labels');
    Route::livewire('/assets/{asset}', ShowAsset::class)->whereUuid('asset')->name('itam.assets.show');
    Route::livewire('/assets/{asset}/edit', AssetForm::class)->whereUuid('asset')->name('itam.assets.edit');
    Route::livewire('/my-equipment', MyEquipment::class)->name('itam.my-equipment');
    Route::get('/assets/handovers/{assignment}', HandoverProtocolController::class)->whereUuid('assignment')->name('itam.handover');
});
