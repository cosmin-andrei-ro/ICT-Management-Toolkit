<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Livewire\ComplianceIndex;
use Modules\Audit\Http\Livewire\ControlForm;
use Modules\Audit\Http\Livewire\ControlIndex;
use Modules\Audit\Http\Livewire\MyControls;
use Modules\Audit\Http\Livewire\ShowControl;
use Modules\Audit\Http\Livewire\ShowFramework;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/compliance', ComplianceIndex::class)->name('audit.index');
    Route::livewire('/compliance/frameworks/{framework}', ShowFramework::class)->whereUuid('framework')->name('audit.frameworks.show');
    Route::livewire('/compliance/controls', ControlIndex::class)->name('audit.controls.index');
    Route::livewire('/compliance/controls/mine', MyControls::class)->name('audit.controls.mine');
    Route::livewire('/compliance/controls/new', ControlForm::class)->name('audit.controls.create');
    Route::livewire('/compliance/controls/{control}', ShowControl::class)->whereUuid('control')->name('audit.controls.show');
    Route::livewire('/compliance/controls/{control}/edit', ControlForm::class)->whereUuid('control')->name('audit.controls.edit');
});
