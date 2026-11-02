<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Http\Livewire\MetricTrend;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/reporting/metrics/{metric}', MetricTrend::class)->where('metric', '[a-z0-9_]+\.[a-z0-9_.]+')->name('reporting.metrics.show');
});
