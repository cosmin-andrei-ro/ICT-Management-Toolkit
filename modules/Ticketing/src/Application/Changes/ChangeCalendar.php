<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Scheduled changes and their overlaps. Overlaps are warnings, not errors: without a
 * CMDB we cannot tell whether two changes touch the same systems.
 */
final class ChangeCalendar
{
    /**
     * Changes with a window intersecting [from, until), submitted or approved, not finished.
     *
     * @return Collection<int, Ticket>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return Ticket::query()
            ->where('type', TicketType::Change)
            ->whereNotIn('status', [TicketStatus::Closed, TicketStatus::Cancelled])
            ->whereHas('changeDetails', fn ($details) => $details
                ->whereIn('approval_status', [ApprovalStatus::Pending, ApprovalStatus::Approved])
                ->where('planned_start', '<', $until)
                ->where('planned_end', '>', $from))
            ->with('changeDetails')
            ->get()
            ->sortBy(fn (Ticket $change) => $change->changeDetails?->planned_start)
            ->values();
    }

    /**
     * Other scheduled changes whose window overlaps this change's window.
     *
     * @return Collection<int, Ticket>
     */
    public function conflictsWith(Ticket $change): Collection
    {
        $details = $change->changeDetails;

        if ($details?->planned_start === null || $details->planned_end === null) {
            return new Collection;
        }

        return $this->between(CarbonImmutable::instance($details->planned_start), CarbonImmutable::instance($details->planned_end))
            ->reject(fn (Ticket $other): bool => $other->id === $change->id)
            ->values();
    }
}
