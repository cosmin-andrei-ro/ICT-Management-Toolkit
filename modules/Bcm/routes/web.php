<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Bcm\Http\Controllers\PlanSheetController;
use Modules\Bcm\Http\Livewire\ExerciseForm;
use Modules\Bcm\Http\Livewire\ExercisePage;
use Modules\Bcm\Http\Livewire\MyPlans;
use Modules\Bcm\Http\Livewire\PlanForm;
use Modules\Bcm\Http\Livewire\PlanIndex;
use Modules\Bcm\Http\Livewire\PlanVersionPage;
use Modules\Bcm\Http\Livewire\ShowPlan;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/bcm', PlanIndex::class)->name('bcm.plans.index');
    Route::livewire('/bcm/mine', MyPlans::class)->name('bcm.mine');
    Route::livewire('/bcm/plans/new', PlanForm::class)->name('bcm.plans.create');
    Route::livewire('/bcm/plans/{plan}', ShowPlan::class)->whereUuid('plan')->name('bcm.plans.show');
    Route::livewire('/bcm/plans/{plan}/edit', PlanForm::class)->whereUuid('plan')->name('bcm.plans.edit');
    Route::get('/bcm/plans/{plan}/print', PlanSheetController::class)->whereUuid('plan')->name('bcm.plans.sheet');
    Route::livewire('/bcm/versions/{version}', PlanVersionPage::class)->whereUuid('version')->name('bcm.versions.show');
    Route::livewire('/bcm/plans/{plan}/exercises/new', ExerciseForm::class)->whereUuid('plan')->name('bcm.exercises.create');
    Route::livewire('/bcm/exercises/{exercise}', ExercisePage::class)->whereUuid('exercise')->name('bcm.exercises.show');
});
