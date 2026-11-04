<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Sla\OlaClock;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Events\TicketAssigned;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Routes an open ticket to a queue and/or a person. The assignee must be able to
 * work tickets in the ticket's organization and, when the ticket has a queue, be
 * a member of that queue's team.
 */
final readonly class AssignTicket
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private TicketLifecycle $lifecycle,
        private Dispatcher $events,
        private OlaClock $ola,
    ) {}

    public function handle(Ticket $ticket, ?User $assignee, ?Queue $queue, User $actor): Ticket
    {
        $this->lifecycle->assertOpen($ticket);

        if ($ticket->status === TicketStatus::AwaitingApproval) {
            throw new InvalidArgumentException('This request waits for approval.');
        }

        if ($queue !== null && ($queue->organization_id !== $ticket->organization_id || ! $queue->is_active)) {
            throw new InvalidArgumentException('The queue must be active and belong to the ticket\'s organization.');
        }

        $queue ??= $ticket->queue;

        if ($assignee !== null) {
            $this->assertCanBeAssigned($ticket, $assignee, $queue);
        }

        $previous = $ticket->assignee;

        $this->db->transaction(function () use ($ticket, $assignee, $queue, $actor): void {
            $ticket->assignee_id = $assignee?->id;
            $ticket->queue_id = $queue?->id;

            $target = match (true) {
                $assignee !== null && $ticket->status === TicketStatus::New => TicketStatus::Assigned,
                $assignee === null && in_array($ticket->status, [TicketStatus::Assigned, TicketStatus::InProgress], true) => TicketStatus::New,
                default => null,
            };

            $target !== null ? $this->lifecycle->transition($ticket, $target, $actor) : $ticket->save();
            $this->ola->queueChanged($ticket);
        });

        if ($assignee !== null && $assignee->id !== $previous?->id) {
            $this->events->dispatch(new TicketAssigned($ticket, $actor, $previous));
        }

        return $ticket;
    }

    private function assertCanBeAssigned(Ticket $ticket, User $assignee, ?Queue $queue): void
    {
        $canWork = $assignee->isMemberOf($ticket->organization_id)
            && $this->context->runAs($ticket->organization_id, fn (): bool => User::query()->findOrFail($assignee->id)->can('ticketing.ticket.work'));

        if (! $canWork) {
            throw new InvalidArgumentException('The assignee cannot work tickets in this organization.');
        }

        if ($queue !== null && ! $queue->team->hasMember($assignee)) {
            throw new InvalidArgumentException('The assignee must be a member of the queue\'s team.');
        }
    }
}
