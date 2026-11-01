<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use DomainException;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Post-implementation review: what happened, why it was needed, what to learn.
 * Required before an emergency change can close.
 */
final readonly class RecordChangeReview
{
    public function handle(Ticket $change, string $review): void
    {
        $details = $change->changeDetails ?? throw new DomainException('Not a change.');
        $review = trim($review);

        if ($change->status !== TicketStatus::Resolved) {
            throw new DomainException('A change is reviewed after its implementation.');
        }

        if ($review === '' || mb_strlen($review) > ChangePlan::MAX_LENGTH) {
            throw new DomainException('The review must be between 1 and '.ChangePlan::MAX_LENGTH.' characters.');
        }

        $details->review = $review;
        $details->reviewed_at = now();
        $details->save();
    }
}
