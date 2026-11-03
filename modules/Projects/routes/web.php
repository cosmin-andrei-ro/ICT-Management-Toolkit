<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\ProjectReportController;
use Modules\Projects\Http\Livewire\MyTasks;
use Modules\Projects\Http\Livewire\Portfolio;
use Modules\Projects\Http\Livewire\ProjectForm;
use Modules\Projects\Http\Livewire\ProjectIndex;
use Modules\Projects\Http\Livewire\ShowProject;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/projects', ProjectIndex::class)->name('projects.index');
    Route::livewire('/projects/new', ProjectForm::class)->name('projects.create');
    Route::livewire('/projects/my-tasks', MyTasks::class)->name('projects.tasks.mine');
    Route::livewire('/projects/portfolio', Portfolio::class)->name('projects.portfolio');
    Route::livewire('/projects/{project}', ShowProject::class)->whereUuid('project')->name('projects.show');
    Route::livewire('/projects/{project}/edit', ProjectForm::class)->whereUuid('project')->name('projects.edit');
    Route::get('/projects/{project}/report', [ProjectReportController::class, 'show'])->whereUuid('project')->name('projects.report');
    Route::get('/projects/{project}/report/pdf', [ProjectReportController::class, 'pdf'])->whereUuid('project')->middleware('throttle:10,1')->name('projects.report.pdf');
});
