<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Documentation\Http\Controllers\PagePrintController;
use Modules\Documentation\Http\Livewire\DocsIndex;
use Modules\Documentation\Http\Livewire\PageEditor;
use Modules\Documentation\Http\Livewire\PageHistory;
use Modules\Documentation\Http\Livewire\ShowPage;
use Modules\Documentation\Http\Livewire\ShowSpace;
use Modules\Documentation\Http\Livewire\SpaceForm;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/docs', DocsIndex::class)->name('documentation.index');
    Route::livewire('/docs/spaces/new', SpaceForm::class)->name('documentation.spaces.create');
    Route::livewire('/docs/spaces/{space}', ShowSpace::class)->whereUuid('space')->name('documentation.spaces.show');
    Route::livewire('/docs/spaces/{space}/edit', SpaceForm::class)->whereUuid('space')->name('documentation.spaces.edit');
    Route::livewire('/docs/spaces/{space}/pages/new', PageEditor::class)->whereUuid('space')->name('documentation.pages.create');
    Route::livewire('/docs/pages/{page}', ShowPage::class)->whereUuid('page')->name('documentation.pages.show');
    Route::livewire('/docs/pages/{page}/edit', PageEditor::class)->whereUuid('page')->name('documentation.pages.edit');
    Route::livewire('/docs/pages/{page}/history', PageHistory::class)->whereUuid('page')->name('documentation.pages.history');
    Route::get('/docs/pages/{page}/print', [PagePrintController::class, 'show'])->whereUuid('page')->name('documentation.pages.print');
    Route::get('/docs/pages/{page}/pdf', [PagePrintController::class, 'pdf'])->whereUuid('page')->middleware('throttle:10,1')->name('documentation.pages.pdf');
});
