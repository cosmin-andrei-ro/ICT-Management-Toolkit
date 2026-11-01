<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

final readonly class CloseTicket
{
    public function __construct(
        private ConnectionInterface $db,
        private TicketLifecycle $lifecycle,
    ) {}

    /**
     * @param  ?User  $actor  Null when closed automatically after the grace period.
     */
    public function handle(Ticket $ticket, ?User $actor): Ticket
    {
        $this->db->transaction(function () use ($ticket, $actor): void {
            $ticket->closed_at = now();
            $this->lifecycle->transition($ticket, TicketStatus::Closed, $actor);
        });

        return $ticket;
    }
}
