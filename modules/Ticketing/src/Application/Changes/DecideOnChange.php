<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Changes\ApprovalDecision;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Events\ChangeApproved;
use Modules\Ticketing\Domain\Events\ChangeRejected;
use Modules\Ticketing\Domain\Models\ChangeApproval;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * A CAB member's decision. One rejection rejects the change; it is approved once
 * every required approver has approved in the current round (owner's decision:
 * unanimity, for CAB and ECAB alike).
 */
final readonly class DecideOnChange
{
    public function __construct(
        private ConnectionInterface $db,
        private CabApprovers $approvers,
        private Dispatcher $events,
    ) {}

    public function handle(Ticket $change, User $approver, ApprovalDecision $decision, string $comment): void
    {
        $comment = trim($comment);

        $outcome = $this->db->transaction(function () use ($change, $approver, $decision, $comment): ?ApprovalStatus {
            $details = $change->changeDetails()->lockForUpdate()->first() ?? throw new DomainException('Not a change.');
            $board = $details->cabBoard;

            if ($details->approval_status !== ApprovalStatus::Pending || $board === null) {
                throw new DomainException('This change is not waiting for approval.');
            }

            $required = $this->approvers->for($change, $board);

            if (! $required->contains('id', $approver->id)) {
                throw new DomainException('Only members of the change\'s board may decide, and never its requester.');
            }

            if ($decision === ApprovalDecision::Rejected && $comment === '') {
                throw new DomainException('A rejection needs a reason.');
            }

            $alreadyDecided = ChangeApproval::query()
                ->where('ticket_id', $change->id)->where('round', $details->approval_round)->where('user_id', $approver->id)
                ->exists();

            if ($alreadyDecided) {
                throw new DomainException('You have already decided on this change.');
            }

            (new ChangeApproval)->forceFill([
                'organization_id' => $change->organization_id,
                'ticket_id' => $change->id,
                'round' => $details->approval_round,
                'user_id' => $approver->id,
                'decision' => $decision,
                'comment' => $comment !== '' ? $comment : null,
                'decided_at' => now(),
            ])->save();

            if ($decision === ApprovalDecision::Rejected) {
                $details->approval_status = ApprovalStatus::Rejected;
                $details->save();

                return ApprovalStatus::Rejected;
            }

            $approvedBy = ChangeApproval::query()
                ->where('ticket_id', $change->id)->where('round', $details->approval_round)
                ->where('decision', ApprovalDecision::Approved)
                ->pluck('user_id');

            if ($required->every(fn (User $user): bool => $approvedBy->contains($user->id))) {
                $details->approval_status = ApprovalStatus::Approved;
                $details->approved_at = now();
                $details->save();

                return ApprovalStatus::Approved;
            }

            return null;
        });

        match ($outcome) {
            ApprovalStatus::Approved => $this->events->dispatch(new ChangeApproved($change, $approver)),
            ApprovalStatus::Rejected => $this->events->dispatch(new ChangeRejected($change, $approver)),
            default => null,
        };
    }
}
