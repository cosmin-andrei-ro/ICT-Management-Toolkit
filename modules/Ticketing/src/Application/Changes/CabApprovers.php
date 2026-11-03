<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Who must approve a change: every active board member allowed to approve, except
 * the change's requester, whoever sent it to the board and whoever last wrote its
 * plan (separation of duties).
 */
final class CabApprovers
{
    /**
     * @return Collection<int, User>
     */
    public function for(Ticket $change, CabBoard $board): Collection
    {
        return $board->members()
            ->where('is_active', true)
            ->whereNull('directory_disabled_at')
            ->whereKeyNot(array_values(array_filter([
                $change->requester_id,
                $change->changeDetails?->submitted_by,
                $change->changeDetails?->planned_by,
            ])))
            ->get()
            ->filter(fn (User $user): bool => $user->can('ticketing.change.approve'))
            ->values();
    }
}
