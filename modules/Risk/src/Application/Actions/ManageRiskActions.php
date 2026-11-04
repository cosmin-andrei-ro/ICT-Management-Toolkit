<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Actions;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\ProblemTickets;
use Modules\Core\Domain\Links\TicketSummary;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Enums\ActionStatus;
use Modules\Risk\Domain\Enums\AssetType;
use Modules\Risk\Domain\Enums\RiskLevel;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * The actions that carry out a risk's treatment, each with an owner and a due
 * date, and optionally tracked as a problem in Ticketing. Audited on
 * the risk.
 */
final readonly class ManageRiskActions
{
    public function __construct(
        private ProblemTickets $tickets,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws InvalidArgumentException for an empty title or an owner outside the organization
     */
    public function add(Risk $risk, string $title, ?string $ownerId, ?CarbonImmutable $dueOn, User $actor): RiskAction
    {
        $title = trim($title);

        if ($title === '' || ($ownerId !== null && ! User::query()->whereKey($ownerId)->whereHas('organizations', fn ($query) => $query->whereKey($risk->organization_id))->exists())) {
            throw new InvalidArgumentException('An action needs a title and an owner of the organization.');
        }

        $action = new RiskAction;
        $action->forceFill([
            'organization_id' => $risk->organization_id,
            'risk_id' => $risk->id,
            'title' => mb_substr($title, 0, 255),
            'owner_id' => $ownerId,
            'due_on' => $dueOn,
        ])->save();

        $this->trail->record(AuditEvent::Updated, $risk, null, ['action_added' => $action->title], $actor->id);

        return $action;
    }

    public function changeStatus(RiskAction $action, ActionStatus $status, User $actor): void
    {
        $before = $action->status;
        $action->forceFill(['status' => $status, 'completed_at' => $status === ActionStatus::Done ? now() : null])->save();
        $risk = Risk::query()->findOrFail($action->risk_id);
        $this->trail->record(AuditEvent::Updated, $risk, ['action' => $action->title.': '.$before->value], ['action' => $action->title.': '.$status->value], $actor->id);
    }

    /**
     * Raises the action as a problem in Ticketing, once, with the risk's CMDB items.
     *
     * @throws InvalidArgumentException when already raised or Ticketing is not available
     */
    public function raiseTicket(RiskAction $action, User $actor): TicketSummary
    {
        if ($action->ticket_id !== null || ! $this->tickets->isAvailable()) {
            throw new InvalidArgumentException('Already raised, or Ticketing is not available.');
        }

        $risk = Risk::query()->findOrFail($action->risk_id);
        $items = array_values(array_map(fn (array $asset): string => $asset['id'], array_filter($risk->assets(), fn (array $asset): bool => $asset['type'] === AssetType::ConfigurationItem->value)));

        $ticket = $this->tickets->raiseProblem(
            __('risk::treatment.ticket.title', ['risk' => $risk->code(), 'action' => mb_substr($action->title, 0, 180)]),
            __('risk::treatment.ticket.description', [
                'risk' => $risk->code().' '.$risk->title,
                'action' => $action->title,
                'due' => $action->due_on?->toDateString() ?? '—',
                'url' => route('risk.risks.show', $risk),
            ]),
            match (RiskSettings::current()->level($risk->currentScore() ?? 1)) {
                RiskLevel::Critical, RiskLevel::High => 'high',
                RiskLevel::Medium => 'medium',
                RiskLevel::Low => 'low',
            },
            $actor,
            $items,
        );

        $action->forceFill(['ticket_id' => $ticket->id, 'ticket_number' => $ticket->number])->save();
        $this->trail->record(AuditEvent::Updated, $risk, null, ['action_ticket' => $ticket->number], $actor->id);

        return $ticket;
    }
}
