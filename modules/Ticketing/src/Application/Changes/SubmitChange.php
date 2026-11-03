<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Events\ChangeSubmitted;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Sends a normal change to a CAB, or an emergency change to an emergency CAB
 * (ECAB), for authorization. Starts a new approval round.
 */
final readonly class SubmitChange
{
    public function __construct(
        private CabApprovers $approvers,
        private Dispatcher $events,
    ) {}

    public function handle(Ticket $change, CabBoard $board, User $actor): void
    {
        $details = $change->changeDetails ?? throw new DomainException('Not a change.');

        if (! $details->change_type->needsCab()) {
            throw new DomainException('Standard changes are pre-approved.');
        }

        if (! in_array($details->approval_status, [ApprovalStatus::Draft, ApprovalStatus::Rejected], true) || $change->status->isFinal()) {
            throw new DomainException('Only a draft or rejected change can be submitted.');
        }

        if ($details->implementation_plan === null || $details->rollback_plan === null || $details->planned_start === null || $details->planned_end === null) {
            throw new DomainException('A change needs an implementation plan, a rollback plan and a window before submission.');
        }

        $wantsEmergencyBoard = $details->change_type === ChangeType::Emergency;

        if (! $board->is_active || $board->organization_id !== $change->organization_id || $board->is_emergency !== $wantsEmergencyBoard) {
            throw new DomainException($wantsEmergencyBoard ? 'Emergency changes go to an emergency CAB (ECAB).' : 'Normal changes go to a regular CAB.');
        }

        // Whoever sends it may not approve it either, so they are left out of the board from here.
        $details->submitted_by = $actor->id;

        if ($this->approvers->for($change, $board)->isEmpty()) {
            throw new DomainException('This board has nobody who could approve the change.');
        }

        $details->cab_board_id = $board->id;
        $details->approval_status = ApprovalStatus::Pending;
        $details->approval_round++;
        $details->save();

        $this->events->dispatch(new ChangeSubmitted($change, $actor));
    }
}
