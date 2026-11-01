<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use DomainException;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Change rules on status transitions, checked by TicketLifecycle for every change:
 * no work before authorization, no closing an emergency change without its review.
 */
final class ChangeGuard
{
    public function assertCanTransition(Ticket $ticket, TicketStatus $to): void
    {
        if ($ticket->type !== TicketType::Change) {
            return;
        }

        $details = $ticket->changeDetails;

        if ($details === null) {
            throw new DomainException('A change needs its details.');
        }

        if (in_array($to, [TicketStatus::InProgress, TicketStatus::Resolved], true) && ! $details->isApproved()) {
            throw new DomainException("Change [{$ticket->number}] is not authorized.");
        }

        if ($to === TicketStatus::Closed && ! $details->canClose()) {
            throw new DomainException("Emergency change [{$ticket->number}] needs its post-implementation review before closing.");
        }
    }
}
