<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Audit\Domain\Enums\ActionStatus;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\ServiceDeskTickets;
use Modules\Core\Domain\Links\TicketSummary;
use Modules\Core\Domain\Models\User;

/**
 * The corrective actions of a finding, each with an owner and a due date,
 * and optionally tracked as a problem in the service desk. Audited on the
 * finding.
 */
final readonly class ManageFindingActions
{
    public function __construct(
        private ServiceDeskTickets $tickets,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws InvalidArgumentException for an empty title or an owner outside the organization
     */
    public function add(Finding $finding, string $title, ?string $ownerId, ?CarbonImmutable $dueOn, User $actor): FindingAction
    {
        $title = trim($title);

        if ($title === '' || ($ownerId !== null && ! User::query()->whereKey($ownerId)->whereHas('organizations', fn ($query) => $query->whereKey($finding->organization_id))->exists())) {
            throw new InvalidArgumentException('An action needs a title and an owner of the organization.');
        }

        $action = new FindingAction;
        $action->forceFill([
            'organization_id' => $finding->organization_id,
            'finding_id' => $finding->id,
            'title' => mb_substr($title, 0, 255),
            'owner_id' => $ownerId,
            'due_on' => $dueOn,
        ])->save();

        $this->trail->record(AuditEvent::Updated, $finding, null, ['action_added' => $action->title], $actor->id);

        return $action;
    }

    public function changeStatus(FindingAction $action, ActionStatus $status, User $actor): void
    {
        $before = $action->status;
        $action->forceFill(['status' => $status, 'completed_at' => $status === ActionStatus::Done ? now() : null])->save();
        $finding = Finding::query()->findOrFail($action->finding_id);
        $this->trail->record(AuditEvent::Updated, $finding, ['action' => $action->title.': '.$before->value], ['action' => $action->title.': '.$status->value], $actor->id);
    }

    /**
     * Raises the action as a problem in the service desk, once.
     *
     * @throws InvalidArgumentException when already raised or there is no service desk
     */
    public function raiseTicket(FindingAction $action, User $actor): TicketSummary
    {
        if ($action->ticket_id !== null || ! $this->tickets->isAvailable()) {
            throw new InvalidArgumentException('Already raised, or there is no service desk.');
        }

        $finding = Finding::query()->findOrFail($action->finding_id);
        $ticket = $this->tickets->raiseProblem(
            __('audit::audits.ticket.title', ['finding' => $finding->code(), 'action' => mb_substr($action->title, 0, 180)]),
            __('audit::audits.ticket.description', [
                'finding' => $finding->code().' '.$finding->title,
                'action' => $action->title,
                'due' => $action->due_on?->toDateString() ?? '—',
                'url' => route('audit.findings.show', $finding),
            ]),
            match ($finding->severity) {
                FindingSeverity::MajorNonconformity => 'high',
                FindingSeverity::MinorNonconformity => 'medium',
                FindingSeverity::Observation, FindingSeverity::Opportunity => 'low',
            },
            $actor,
        );

        $action->forceFill(['ticket_id' => $ticket->id, 'ticket_number' => $ticket->number])->save();
        $this->trail->record(AuditEvent::Updated, $finding, null, ['action_ticket' => $ticket->number], $actor->id);

        return $ticket;
    }
}
