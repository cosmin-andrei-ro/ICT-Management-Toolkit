<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Sla\SlaClock;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Events\TicketCommentAdded;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Comments on a ticket. A public reply from IT support counts as the first
 * response; a reply from the requester takes a pending ticket back into work.
 */
final readonly class AddTicketComment
{
    public function __construct(
        private ConnectionInterface $db,
        private TicketLifecycle $lifecycle,
        private AddComment $addComment,
        private SlaClock $sla,
        private Dispatcher $events,
    ) {}

    public function handle(Ticket $ticket, User $author, string $body, bool $internal): Comment
    {
        $this->lifecycle->assertNotFinal($ticket);

        $comment = $this->db->transaction(function () use ($ticket, $author, $body, $internal): Comment {
            $comment = $this->addComment->handle($ticket, $author, $body, $internal);

            if (! $internal && ! $ticket->isRequester($author) && $author->can('ticketing.ticket.work')) {
                $ticket->first_responded_at ??= now();
                $ticket->save();
                $this->sla->responded($ticket);
            }

            if ($ticket->isRequester($author) && $ticket->status === TicketStatus::Pending) {
                $this->lifecycle->transition($ticket, $ticket->assignee_id !== null ? TicketStatus::InProgress : TicketStatus::New, $author);
            }

            return $comment;
        });

        $this->events->dispatch(new TicketCommentAdded($ticket, $author, $comment));

        return $comment;
    }
}
