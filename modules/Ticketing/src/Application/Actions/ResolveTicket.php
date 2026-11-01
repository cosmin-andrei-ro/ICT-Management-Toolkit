<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Events\TicketResolved;
use Modules\Ticketing\Domain\Models\Ticket;

final readonly class ResolveTicket
{
    public function __construct(
        private ConnectionInterface $db,
        private TicketLifecycle $lifecycle,
        private AddComment $addComment,
        private Dispatcher $events,
    ) {}

    /**
     * The note is also posted as a public comment, so the requester sees what was done.
     */
    public function handle(Ticket $ticket, ResolutionCode $code, string $note, User $actor): Ticket
    {
        $note = trim($note);

        $this->db->transaction(function () use ($ticket, $code, $note, $actor): void {
            $ticket->assignee_id ??= $actor->id;
            $ticket->resolution_code = $code;
            $ticket->resolution_note = $note !== '' ? $note : null;
            $ticket->resolved_at = now();
            $ticket->first_responded_at ??= now();

            $this->lifecycle->transition($ticket, TicketStatus::Resolved, $actor);

            if ($note !== '') {
                $this->addComment->handle($ticket, $actor, $note, internal: false);
            }
        });

        $this->events->dispatch(new TicketResolved($ticket, $actor));

        return $ticket;
    }
}
