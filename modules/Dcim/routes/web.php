<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Dcim\Http\Livewire\RackForm;
use Modules\Dcim\Http\Livewire\RackIndex;
use Modules\Dcim\Http\Livewire\ShowRack;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/dcim/racks', RackIndex::class)->name('dcim.racks.index');
    Route::livewire('/dcim/racks/new', RackForm::class)->name('dcim.racks.create');
    Route::livewire('/dcim/racks/{rack}', ShowRack::class)->whereUuid('rack')->name('dcim.racks.show');
    Route::livewire('/dcim/racks/{rack}/edit', RackForm::class)->whereUuid('rack')->name('dcim.racks.edit');
});
