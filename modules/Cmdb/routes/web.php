<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cmdb\Http\Livewire\ImportPage;
use Modules\Cmdb\Http\Livewire\ItemForm;
use Modules\Cmdb\Http\Livewire\ItemIndex;
use Modules\Cmdb\Http\Livewire\ShowItem;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/cmdb', ItemIndex::class)->name('cmdb.items.index');
    Route::livewire('/cmdb/new', ItemForm::class)->name('cmdb.items.create');
    Route::livewire('/cmdb/import', ImportPage::class)->name('cmdb.import');
    Route::livewire('/cmdb/{item}', ShowItem::class)->whereUuid('item')->name('cmdb.items.show');
    Route::livewire('/cmdb/{item}/edit', ItemForm::class)->whereUuid('item')->name('cmdb.items.edit');
});
