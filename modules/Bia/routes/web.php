<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Bia\Http\Livewire\AssessmentPage;
use Modules\Bia\Http\Livewire\GapReport;
use Modules\Bia\Http\Livewire\MyProcesses;
use Modules\Bia\Http\Livewire\ProcessForm;
use Modules\Bia\Http\Livewire\ProcessIndex;
use Modules\Bia\Http\Livewire\ShowProcess;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/bia', ProcessIndex::class)->name('bia.processes.index');
    Route::livewire('/bia/mine', MyProcesses::class)->name('bia.mine');
    Route::livewire('/bia/gaps', GapReport::class)->name('bia.gaps');
    Route::livewire('/bia/processes/new', ProcessForm::class)->name('bia.processes.create');
    Route::livewire('/bia/processes/{process}', ShowProcess::class)->whereUuid('process')->name('bia.processes.show');
    Route::livewire('/bia/processes/{process}/edit', ProcessForm::class)->whereUuid('process')->name('bia.processes.edit');
    Route::livewire('/bia/assessments/{assessment}', AssessmentPage::class)->whereUuid('assessment')->name('bia.assessments.show');
});
