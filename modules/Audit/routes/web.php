<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\ComplianceReportController;
use Modules\Audit\Http\Livewire\AuditForm;
use Modules\Audit\Http\Livewire\AuditIndex;
use Modules\Audit\Http\Livewire\ComplianceIndex;
use Modules\Audit\Http\Livewire\ControlForm;
use Modules\Audit\Http\Livewire\ControlIndex;
use Modules\Audit\Http\Livewire\FindingForm;
use Modules\Audit\Http\Livewire\FindingIndex;
use Modules\Audit\Http\Livewire\MyControls;
use Modules\Audit\Http\Livewire\ShowAudit;
use Modules\Audit\Http\Livewire\ShowControl;
use Modules\Audit\Http\Livewire\ShowFinding;
use Modules\Audit\Http\Livewire\ShowFramework;

Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/compliance', ComplianceIndex::class)->name('audit.index');
    Route::get('/compliance/report', [ComplianceReportController::class, 'show'])->name('audit.report');
    Route::get('/compliance/report/pdf', [ComplianceReportController::class, 'pdf'])->middleware('throttle:10,1')->name('audit.report.pdf');
    Route::livewire('/compliance/frameworks/{framework}', ShowFramework::class)->whereUuid('framework')->name('audit.frameworks.show');
    Route::get('/compliance/frameworks/{framework}/soa', [ComplianceReportController::class, 'soa'])->whereUuid('framework')->name('audit.frameworks.soa');
    Route::get('/compliance/frameworks/{framework}/soa/pdf', [ComplianceReportController::class, 'soaPdf'])->whereUuid('framework')->middleware('throttle:10,1')->name('audit.frameworks.soa.pdf');
    Route::livewire('/compliance/controls', ControlIndex::class)->name('audit.controls.index');
    Route::livewire('/compliance/controls/mine', MyControls::class)->name('audit.controls.mine');
    Route::livewire('/compliance/controls/new', ControlForm::class)->name('audit.controls.create');
    Route::livewire('/compliance/controls/{control}', ShowControl::class)->whereUuid('control')->name('audit.controls.show');
    Route::livewire('/compliance/controls/{control}/edit', ControlForm::class)->whereUuid('control')->name('audit.controls.edit');
    Route::livewire('/compliance/audits', AuditIndex::class)->name('audit.audits.index');
    Route::livewire('/compliance/audits/new', AuditForm::class)->name('audit.audits.create');
    Route::livewire('/compliance/audits/{audit}', ShowAudit::class)->whereUuid('audit')->name('audit.audits.show');
    Route::livewire('/compliance/audits/{audit}/edit', AuditForm::class)->whereUuid('audit')->name('audit.audits.edit');
    Route::livewire('/compliance/findings', FindingIndex::class)->name('audit.findings.index');
    Route::livewire('/compliance/findings/new', FindingForm::class)->name('audit.findings.create');
    Route::livewire('/compliance/findings/{finding}', ShowFinding::class)->whereUuid('finding')->name('audit.findings.show');
    Route::livewire('/compliance/findings/{finding}/edit', FindingForm::class)->whereUuid('finding')->name('audit.findings.edit');
});
