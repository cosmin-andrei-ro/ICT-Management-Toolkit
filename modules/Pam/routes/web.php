<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pam\Http\Livewire\AccessLog;
use Modules\Pam\Http\Livewire\AccountForm;
use Modules\Pam\Http\Livewire\AccountIndex;
use Modules\Pam\Http\Livewire\ShowAccount;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/privileged-access', AccountIndex::class)->name('pam.accounts.index');
    Route::livewire('/privileged-access/new', AccountForm::class)->name('pam.accounts.create');
    Route::livewire('/privileged-access/log', AccessLog::class)->name('pam.access-log');
    Route::livewire('/privileged-access/{account}', ShowAccount::class)->whereUuid('account')->name('pam.accounts.show');
    Route::livewire('/privileged-access/{account}/edit', AccountForm::class)->whereUuid('account')->name('pam.accounts.edit');
});
