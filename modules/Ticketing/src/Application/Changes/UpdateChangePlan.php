<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use DomainException;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Edits plans and window before implementation. Any edit to a normal or emergency
 * change that is submitted or approved sends it back to draft: the CAB approved
 * the plan as it was. Standard changes only move their window.
 */
final readonly class UpdateChangePlan
{
    /**
     * @return bool Whether earlier approvals were withdrawn.
     */
    public function handle(Ticket $change, ChangePlan $plan, User $actor): bool
    {
        $details = $change->changeDetails ?? throw new DomainException('Not a change.');

        if (! in_array($change->status, [TicketStatus::New, TicketStatus::Assigned], true)) {
            throw new DomainException('A change can only be re-planned before implementation starts.');
        }

        if ($details->change_type === ChangeType::Standard) {
            $details->planned_start = $plan->plannedStart;
            $details->planned_end = $plan->plannedEnd;
            $details->save();

            return false;
        }

        $details->risk = $plan->risk;
        $details->implementation_plan = CreateChange::orNull($plan->implementationPlan);
        $details->rollback_plan = CreateChange::orNull($plan->rollbackPlan);
        $details->test_plan = CreateChange::orNull($plan->testPlan);
        $details->planned_start = $plan->plannedStart;
        $details->planned_end = $plan->plannedEnd;

        $withdrawn = in_array($details->approval_status, [ApprovalStatus::Pending, ApprovalStatus::Approved], true) && $details->isDirty();

        if ($details->isDirty()) {
            // Whoever wrote the plan may not approve it (CabApprovers).
            $details->planned_by = $actor->id;
        }

        if ($withdrawn) {
            $details->approval_status = ApprovalStatus::Draft;
            $details->approved_at = null;
        }

        $details->save();

        return $withdrawn;
    }
}
