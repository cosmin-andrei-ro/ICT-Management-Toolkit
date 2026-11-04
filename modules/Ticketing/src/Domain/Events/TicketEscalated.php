<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Events;

use Modules\Ticketing\Domain\Models\Ticket;

/**
 * A missed target is still open after the queue's delay: its escalation person is told.
 */
final readonly class TicketEscalated
{
    public function __construct(public Ticket $ticket) {}
}
