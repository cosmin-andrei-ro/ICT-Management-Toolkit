<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ticketing\Http\Livewire\Catalog\RequestServiceForm;
use Modules\Ticketing\Http\Livewire\Catalog\ServiceCatalog;
use Modules\Ticketing\Http\Livewire\Desk\KnownErrors;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket as DeskShowTicket;
use Modules\Ticketing\Http\Livewire\Desk\TicketQueue;
use Modules\Ticketing\Http\Livewire\Portal\CreateTicket;
use Modules\Ticketing\Http\Livewire\Portal\ShowTicket as PortalShowTicket;
use Modules\Ticketing\Http\Livewire\Portal\TicketIndex;

// URLs carry the ticket UUID, never its sequential number (no enumeration).
Route::middleware(['auth', 'organization'])->group(function (): void {
    Route::livewire('/catalog', ServiceCatalog::class)->name('ticketing.catalog.index');
    Route::livewire('/catalog/{service}', RequestServiceForm::class)->whereUuid('service')->name('ticketing.catalog.request');

    Route::livewire('/tickets', TicketIndex::class)->name('ticketing.portal.index');
    Route::livewire('/tickets/new', CreateTicket::class)->name('ticketing.portal.create');
    Route::livewire('/tickets/{ticket}', PortalShowTicket::class)->whereUuid('ticket')->name('ticketing.portal.show');

    Route::livewire('/desk', TicketQueue::class)->name('ticketing.desk.index');
    Route::livewire('/desk/known-errors', KnownErrors::class)->name('ticketing.desk.known-errors');
    Route::livewire('/desk/tickets/{ticket}', DeskShowTicket::class)->whereUuid('ticket')->name('ticketing.desk.show');
});
