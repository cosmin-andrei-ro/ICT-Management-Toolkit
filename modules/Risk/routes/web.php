<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Risk\Http\Livewire\MyRisks;
use Modules\Risk\Http\Livewire\RiskForm;
use Modules\Risk\Http\Livewire\RiskIndex;
use Modules\Risk\Http\Livewire\ShowRisk;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/risks', RiskIndex::class)->name('risk.index');
    Route::livewire('/risks/mine', MyRisks::class)->name('risk.mine');
    Route::livewire('/risks/new', RiskForm::class)->name('risk.risks.create');
    Route::livewire('/risks/{risk}', ShowRisk::class)->whereUuid('risk')->name('risk.risks.show');
    Route::livewire('/risks/{risk}/edit', RiskForm::class)->whereUuid('risk')->name('risk.risks.edit');
});
