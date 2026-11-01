<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * A resolved ticket goes back to its assignee (or to the queue if it has none).
 */
final readonly class ReopenTicket
{
    public function __construct(
        private ConnectionInterface $db,
        private TicketLifecycle $lifecycle,
        private AddComment $addComment,
    ) {}

    public function handle(Ticket $ticket, string $reason, User $actor): Ticket
    {
        $this->db->transaction(function () use ($ticket, $reason, $actor): void {
            $ticket->resolved_at = null;
            $ticket->resolution_code = null;
            $ticket->resolution_note = null;
            $ticket->reopened_count++;

            $this->lifecycle->transition($ticket, $ticket->assignee_id !== null ? TicketStatus::InProgress : TicketStatus::New, $actor);

            if (trim($reason) !== '') {
                $this->addComment->handle($ticket, $actor, $reason, internal: false);
            }
        });

        return $ticket;
    }
}
