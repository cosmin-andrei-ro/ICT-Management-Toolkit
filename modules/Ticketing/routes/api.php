<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ticketing\Http\Controllers\Api\TicketCommentController;
use Modules\Ticketing\Http\Controllers\Api\TicketController;

// Mounted under /api/v1 by the module provider. Token abilities narrow what a token
// may do; policies still check the user's own permissions on every request.
Route::middleware(['organization:sanctum', 'auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('/tickets', [TicketController::class, 'index'])->middleware('abilities:tickets:read')->name('api.v1.tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->middleware('abilities:tickets:write')->name('api.v1.tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->whereUuid('ticket')->middleware('abilities:tickets:read')->name('api.v1.tickets.show');
    Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->whereUuid('ticket')->middleware('abilities:tickets:write')->name('api.v1.tickets.comments.store');
});
