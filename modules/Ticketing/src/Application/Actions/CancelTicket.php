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

final readonly class CancelTicket
{
    public function __construct(
        private ConnectionInterface $db,
        private TicketLifecycle $lifecycle,
        private AddComment $addComment,
    ) {}

    public function handle(Ticket $ticket, string $reason, User $actor): Ticket
    {
        $this->db->transaction(function () use ($ticket, $reason, $actor): void {
            $this->lifecycle->transition($ticket, TicketStatus::Cancelled, $actor);

            if (trim($reason) !== '') {
                $this->addComment->handle($ticket, $actor, $reason, internal: false);
            }
        });

        return $ticket;
    }
}
