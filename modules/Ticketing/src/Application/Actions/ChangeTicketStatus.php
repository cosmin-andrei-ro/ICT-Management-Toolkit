<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Working states only (in progress, pending, back to new). Resolving, closing,
 * reopening and cancelling have their own actions.
 */
final readonly class ChangeTicketStatus
{
    private const array ALLOWED_TARGETS = [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Pending];

    public function __construct(
        private ConnectionInterface $db,
        private TicketLifecycle $lifecycle,
    ) {}

    public function handle(Ticket $ticket, TicketStatus $to, User $actor): Ticket
    {
        if (! in_array($to, self::ALLOWED_TARGETS, true)) {
            throw new InvalidArgumentException("Use the dedicated action to move a ticket to [{$to->value}].");
        }

        $this->db->transaction(function () use ($ticket, $to, $actor): void {
            // Starting work on an unowned ticket takes it.
            if ($to === TicketStatus::InProgress && $ticket->assignee_id === null) {
                $ticket->assignee_id = $actor->id;
            }

            if ($to === TicketStatus::New) {
                $ticket->assignee_id = null;
            }

            $this->lifecycle->transition($ticket, $to, $actor);
        });

        return $ticket;
    }
}
