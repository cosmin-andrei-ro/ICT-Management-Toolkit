<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Changes\ChangeGuard;
use Modules\Ticketing\Application\Sla\SlaClock;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Events\TicketStatusChanged;
use Modules\Ticketing\Domain\Models\StatusTransition;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Workflow\TicketWorkflow;

/**
 * The single place where a ticket's status changes: validates the transition,
 * records it for reporting and dispatches the event. Callers set any other
 * attributes on the ticket first, and run this inside their transaction.
 * SLA clocks follow every status change from here.
 */
final readonly class TicketLifecycle
{
    public function __construct(
        private TicketWorkflow $workflow,
        private Dispatcher $events,
        private SlaClock $sla,
        private ChangeGuard $changes,
    ) {}

    public function transition(Ticket $ticket, TicketStatus $to, ?User $actor): void
    {
        $from = $ticket->status;
        $this->workflow->assertCanTransition($from, $to);
        $this->changes->assertCanTransition($ticket, $to);

        $ticket->status = $to;
        $ticket->save();

        $this->record($ticket, $from, $to, $actor);
        $this->sla->onTransition($ticket, $from, $to);
        $this->events->dispatch(new TicketStatusChanged($ticket, $actor, $from, $to));
    }

    /**
     * A catalog request was approved: it starts its normal life, and its clocks start running.
     */
    public function approve(Ticket $ticket, ?User $actor): void
    {
        $ticket->status = TicketStatus::New;
        $ticket->save();

        $this->record($ticket, TicketStatus::AwaitingApproval, TicketStatus::New, $actor);
        $this->sla->onTransition($ticket, TicketStatus::AwaitingApproval, TicketStatus::New);
        $this->events->dispatch(new TicketStatusChanged($ticket, $actor, TicketStatus::AwaitingApproval, TicketStatus::New));
    }

    public function record(Ticket $ticket, ?TicketStatus $from, TicketStatus $to, ?User $actor): void
    {
        (new StatusTransition)->forceFill([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor?->id,
        ])->save();
    }

    /**
     * @throws TicketNotOpen
     */
    public function assertOpen(Ticket $ticket): void
    {
        if (! $ticket->status->isOpen()) {
            throw new TicketNotOpen($ticket);
        }
    }

    /**
     * @throws TicketNotOpen
     */
    public function assertNotFinal(Ticket $ticket): void
    {
        if ($ticket->status->isFinal()) {
            throw new TicketNotOpen($ticket);
        }
    }
}
