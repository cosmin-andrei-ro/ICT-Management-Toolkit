<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Enums;

/**
 * Mirrored by ticketing_tickets_status_check. Transitions: TicketWorkflow.
 */
enum TicketStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Open tickets still need work from IT support or the requester.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed, self::Cancelled], true);
    }

    /**
     * Final states accept no further change, comment or attachment.
     */
    public function isFinal(): bool
    {
        return $this === self::Closed || $this === self::Cancelled;
    }

    public function label(): string
    {
        return __('ticketing::tickets.statuses.'.$this->value);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }
}
