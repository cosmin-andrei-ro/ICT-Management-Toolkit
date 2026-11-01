<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Requesters see their own tickets and those they watch; agents (view_all) see
 * every ticket of the organization. Internal comments and attachments are for
 * agents only. Also provides the abilities Core comments and attachments rely on.
 */
final readonly class TicketPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('ticketing.ticket.create') || $user->can('ticketing.ticket.view_all');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (! $this->inCurrentOrganization($ticket)) {
            return false;
        }

        return $user->can('ticketing.ticket.view_all') || $ticket->isRequester($user) || $ticket->isWatchedBy($user);
    }

    public function viewInternal(User $user, Ticket $ticket): bool
    {
        return $this->inCurrentOrganization($ticket) && $user->can('ticketing.ticket.view_all');
    }

    public function create(User $user): bool
    {
        return $user->can('ticketing.ticket.create');
    }

    /**
     * Logging a ticket on behalf of someone else (phone, walk-in).
     */
    public function createOnBehalf(User $user): bool
    {
        return $user->can('ticketing.ticket.work');
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return ! $ticket->status->isFinal() && $this->view($user, $ticket);
    }

    public function commentInternally(User $user, Ticket $ticket): bool
    {
        return ! $ticket->status->isFinal() && $this->work($user, $ticket);
    }

    /**
     * Triage, working states and resolution.
     */
    public function work(User $user, Ticket $ticket): bool
    {
        return $this->inCurrentOrganization($ticket) && $user->can('ticketing.ticket.work');
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $ticket->status->isOpen() && $this->inCurrentOrganization($ticket) && $user->can('ticketing.ticket.assign');
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        return $ticket->status === TicketStatus::Resolved
            && ($this->work($user, $ticket) || ($this->inCurrentOrganization($ticket) && $ticket->isRequester($user)));
    }

    /**
     * The requester confirms the resolution; agents may close it for them.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        return $this->reopen($user, $ticket);
    }

    /**
     * Requesters may withdraw a ticket nobody has started on; agents may cancel any open one.
     */
    public function cancel(User $user, Ticket $ticket): bool
    {
        if (! $ticket->status->isOpen()) {
            return false;
        }

        return $this->work($user, $ticket)
            || ($this->inCurrentOrganization($ticket) && $ticket->isRequester($user) && $ticket->status === TicketStatus::New);
    }

    public function createProblem(User $user): bool
    {
        return $user->can('ticketing.problem.manage');
    }

    /**
     * Root cause, workaround, known error and resolution of a problem.
     */
    public function manageProblem(User $user, Ticket $ticket): bool
    {
        return $ticket->type === TicketType::Problem
            && ! $ticket->status->isFinal()
            && $this->inCurrentOrganization($ticket)
            && $user->can('ticketing.problem.manage');
    }

    /**
     * Linking an incident to the problem behind it.
     */
    public function linkProblem(User $user, Ticket $ticket): bool
    {
        return $ticket->type === TicketType::Incident && ! $ticket->status->isFinal() && $this->work($user, $ticket);
    }

    private function inCurrentOrganization(Ticket $ticket): bool
    {
        return $this->context->has() && $ticket->organization_id === $this->context->id();
    }
}
