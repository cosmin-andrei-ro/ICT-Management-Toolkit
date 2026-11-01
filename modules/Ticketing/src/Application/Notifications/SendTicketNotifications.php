<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Notifications;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Email\ReplyAddress;
use Modules\Ticketing\Domain\Events\TicketAssigned;
use Modules\Ticketing\Domain\Events\TicketCommentAdded;
use Modules\Ticketing\Domain\Events\TicketCreated;
use Modules\Ticketing\Domain\Events\TicketResolved;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Who hears about what. Nobody is notified of their own action, accounts that
 * cannot sign in are skipped, and internal notes never reach requesters.
 */
final readonly class SendTicketNotifications
{
    public function __construct(private ReplyAddress $replyAddress) {}

    public function handle(TicketCreated|TicketAssigned|TicketResolved|TicketCommentAdded $event): void
    {
        $ticket = $event->ticket;

        // Actions change assignee_id on an instance that may hold stale relations.
        $ticket->load(['requester', 'assignee', 'watchers']);

        match (true) {
            $event instanceof TicketCreated => $this->notifyRequesterSide($ticket, 'created', [$ticket->requester], null),
            $event instanceof TicketAssigned => $this->notifyDeskSide($ticket, 'assigned', [$ticket->assignee], $event->actor),
            $event instanceof TicketResolved => $this->notifyRequesterSide($ticket, 'resolved', [$ticket->requester], $event->actor),
            $event instanceof TicketCommentAdded => $this->onComment($event),
        };
    }

    private function onComment(TicketCommentAdded $event): void
    {
        if ($event->comment->is_internal) {
            return;
        }

        $ticket = $event->ticket;

        if ($event->actor !== null && $ticket->isRequester($event->actor)) {
            $this->notifyDeskSide($ticket, 'requester_replied', [$ticket->assignee], $event->actor);

            return;
        }

        $this->notifyRequesterSide($ticket, 'desk_replied', [$ticket->requester, ...$ticket->watchers], $event->actor);
    }

    /**
     * @param  iterable<int, User|null>  $recipients
     */
    private function notifyRequesterSide(Ticket $ticket, string $kind, iterable $recipients, ?User $actor): void
    {
        $this->send($recipients, $actor, new TicketUpdate($kind, $ticket->number, $ticket->title, route('ticketing.portal.show', $ticket), $this->replyAddress->for($ticket->organization_id)));
    }

    /**
     * @param  iterable<int, User|null>  $recipients
     */
    private function notifyDeskSide(Ticket $ticket, string $kind, iterable $recipients, ?User $actor): void
    {
        $this->send($recipients, $actor, new TicketUpdate($kind, $ticket->number, $ticket->title, route('ticketing.desk.show', $ticket), $this->replyAddress->for($ticket->organization_id)));
    }

    /**
     * @param  iterable<int, User|null>  $recipients
     */
    private function send(iterable $recipients, ?User $actor, TicketUpdate $notification): void
    {
        Collection::make($recipients)
            ->filter(fn (?User $user): bool => $user !== null && $user->canSignIn() && $user->id !== $actor?->id)
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));
    }
}
