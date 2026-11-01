<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use DomainException;
use Modules\Ticketing\Domain\Models\Ticket;

final class TicketNotOpen extends DomainException
{
    public function __construct(Ticket $ticket)
    {
        parent::__construct("Ticket [{$ticket->number}] is {$ticket->status->value} and cannot be changed this way.");
    }
}
