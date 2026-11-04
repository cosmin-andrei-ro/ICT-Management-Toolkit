<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Workflow;

use Modules\Ticketing\Domain\Enums\TicketStatus;

/**
 * Allowed status transitions for incidents and service requests.
 * Closed and cancelled are final.
 */
final class TicketWorkflow
{
    /** @var array<string, list<TicketStatus>> */
    private const array TRANSITIONS = [
        // Approval itself goes through TicketLifecycle::approve: no agent can skip it by changing the status.
        'awaiting_approval' => [TicketStatus::Cancelled],
        'new' => [TicketStatus::Assigned, TicketStatus::InProgress, TicketStatus::Pending, TicketStatus::Resolved, TicketStatus::Cancelled],
        'assigned' => [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Pending, TicketStatus::Resolved, TicketStatus::Cancelled],
        'in_progress' => [TicketStatus::Assigned, TicketStatus::Pending, TicketStatus::Resolved, TicketStatus::Cancelled],
        // Back to "new" when nobody owns the ticket any more (e.g. the assignee was removed).
        'pending' => [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Resolved, TicketStatus::Cancelled],
        'resolved' => [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Closed],
        'closed' => [],
        'cancelled' => [],
    ];

    public function canTransition(TicketStatus $from, TicketStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<TicketStatus>
     */
    public function allowedFrom(TicketStatus $from): array
    {
        return self::TRANSITIONS[$from->value];
    }

    /**
     * @throws InvalidTransition
     */
    public function assertCanTransition(TicketStatus $from, TicketStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidTransition($from, $to);
        }
    }
}
