<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ticketing\Http\Livewire\Catalog\RequestServiceForm;
use Modules\Ticketing\Http\Livewire\Catalog\ServiceCatalog;
use Modules\Ticketing\Http\Livewire\Changes\Approvals;
use Modules\Ticketing\Http\Livewire\Changes\ChangeCalendarView;
use Modules\Ticketing\Http\Livewire\Changes\CreateChange;
use Modules\Ticketing\Http\Livewire\Changes\ReviewChange;
use Modules\Ticketing\Http\Livewire\Desk\KnownErrors;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket as DeskShowTicket;
use Modules\Ticketing\Http\Livewire\Desk\TicketQueue;
use Modules\Ticketing\Http\Livewire\Portal\CreateTicket;
use Modules\Ticketing\Http\Livewire\Portal\ShowTicket as PortalShowTicket;
use Modules\Ticketing\Http\Livewire\Portal\TicketIndex;
use Modules\Ticketing\Http\Livewire\Reports\TicketReports;

// URLs carry the ticket UUID, never its sequential number (no enumeration).
Route::middleware(['auth', 'auth.session', 'organization', 'mfa'])->group(function (): void {
    Route::livewire('/catalog', ServiceCatalog::class)->name('ticketing.catalog.index');
    Route::livewire('/catalog/{service}', RequestServiceForm::class)->whereUuid('service')->name('ticketing.catalog.request');

    Route::livewire('/tickets', TicketIndex::class)->name('ticketing.portal.index');
    Route::livewire('/tickets/new', CreateTicket::class)->name('ticketing.portal.create');
    Route::livewire('/tickets/{ticket}', PortalShowTicket::class)->whereUuid('ticket')->name('ticketing.portal.show');

    Route::livewire('/desk', TicketQueue::class)->name('ticketing.desk.index');
    Route::livewire('/desk/known-errors', KnownErrors::class)->name('ticketing.desk.known-errors');
    Route::livewire('/desk/reports', TicketReports::class)->name('ticketing.reports');
    Route::livewire('/desk/changes', ChangeCalendarView::class)->name('ticketing.changes.calendar');
    Route::livewire('/desk/changes/new', CreateChange::class)->name('ticketing.changes.create');

    Route::livewire('/approvals', Approvals::class)->name('ticketing.changes.approvals');
    Route::livewire('/approvals/{ticket}', ReviewChange::class)->whereUuid('ticket')->name('ticketing.changes.approvals.review');
    Route::livewire('/desk/tickets/{ticket}', DeskShowTicket::class)->whereUuid('ticket')->name('ticketing.desk.show');
});
