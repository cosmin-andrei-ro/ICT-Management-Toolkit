<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Applications\Http\Controllers\DiagramImageController;
use Modules\Applications\Http\Controllers\SheetDocumentController;
use Modules\Applications\Http\Livewire\ApplicationForm;
use Modules\Applications\Http\Livewire\ApplicationIndex;
use Modules\Applications\Http\Livewire\MyApplications;
use Modules\Applications\Http\Livewire\SheetPage;
use Modules\Applications\Http\Livewire\ShowApplication;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/applications', ApplicationIndex::class)->name('applications.index');
    Route::livewire('/applications/mine', MyApplications::class)->name('applications.mine');
    Route::livewire('/applications/new', ApplicationForm::class)->name('applications.create');
    Route::livewire('/applications/{application}', ShowApplication::class)->whereUuid('application')->name('applications.show');
    Route::livewire('/applications/{application}/edit', ApplicationForm::class)->whereUuid('application')->name('applications.edit');
    Route::livewire('/applications/sheets/{sheet}', SheetPage::class)->whereUuid('sheet')->name('applications.sheets.show');
    Route::get('/applications/sheets/{sheet}/document', [SheetDocumentController::class, 'show'])->whereUuid('sheet')->name('applications.sheets.document');
    Route::get('/applications/sheets/{sheet}/pdf', [SheetDocumentController::class, 'pdf'])->whereUuid('sheet')->middleware('throttle:10,1')->name('applications.sheets.pdf');
    Route::get('/applications/diagrams/{attachment}', DiagramImageController::class)->whereUuid('attachment')->name('applications.diagrams.image');
});
