<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exercises;

use InvalidArgumentException;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\FindingSeverity;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\ExerciseStep;
use Modules\Bcm\Domain\Models\Finding;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\ServiceDeskTickets;
use Modules\Core\Domain\Links\TicketSummary;
use Modules\Core\Domain\Models\User;

/**
 * What an exercise showed must be fixed, and the problems raised for it in the
 * service desk (owner's decision). Audited on the exercise.
 */
final readonly class ManageFindings
{
    public function __construct(
        private ServiceDeskTickets $tickets,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws ExerciseClosed unless the exercise is in progress
     * @throws InvalidArgumentException for an empty description or a step of another exercise
     */
    public function add(Exercise $exercise, string $description, FindingSeverity $severity, ?string $stepId, User $actor): Finding
    {
        if ($exercise->status !== ExerciseStatus::InProgress) {
            throw new ExerciseClosed;
        }

        $description = trim($description);
        $stepId = $stepId !== null && $stepId !== '' ? $stepId : null;

        if ($description === '' || ($stepId !== null && ! ExerciseStep::query()->where('exercise_id', $exercise->id)->whereKey($stepId)->exists())) {
            throw new InvalidArgumentException('A finding needs a description, and its step must belong to the exercise.');
        }

        $finding = new Finding;
        $finding->forceFill([
            'organization_id' => $exercise->organization_id,
            'exercise_id' => $exercise->id,
            'exercise_step_id' => $stepId,
            'description' => mb_substr($description, 0, 5000),
            'severity' => $severity,
            'created_by' => $actor->id,
        ])->save();

        $this->trail->record(AuditEvent::Updated, $exercise, null, ['finding_added' => mb_substr($description, 0, 200)], $actor->id);

        return $finding;
    }

    /**
     * @throws ExerciseClosed unless the exercise is in progress
     * @throws InvalidArgumentException once a problem was raised for it
     */
    public function remove(Finding $finding, User $actor): void
    {
        $exercise = Exercise::query()->findOrFail($finding->exercise_id);

        if ($exercise->status !== ExerciseStatus::InProgress) {
            throw new ExerciseClosed;
        }

        if ($finding->ticket_id !== null) {
            throw new InvalidArgumentException('The finding is tracked in the service desk.');
        }

        $finding->delete();
        $this->trail->record(AuditEvent::Updated, $exercise, ['finding_removed' => mb_substr($finding->description, 0, 200)], null, $actor->id);
    }

    /**
     * Raises a problem for the finding, once, with the configuration item of its step.
     *
     * @throws InvalidArgumentException when a problem was already raised or the service desk is not available
     */
    public function raiseTicket(Finding $finding, User $actor): TicketSummary
    {
        if ($finding->ticket_id !== null || ! $this->tickets->isAvailable()) {
            throw new InvalidArgumentException('A problem was already raised, or there is no service desk.');
        }

        $exercise = Exercise::query()->with('plan')->findOrFail($finding->exercise_id);
        $step = $finding->step;

        $ticket = $this->tickets->raiseProblem(
            __('bcm::exercises.ticket.title', ['plan' => $exercise->plan->name, 'finding' => mb_substr($finding->description, 0, 150)]),
            __('bcm::exercises.ticket.description', [
                'exercise' => $exercise->title,
                'date' => ($exercise->started_at ?? $exercise->scheduled_on)->toDateString(),
                'step' => $step !== null ? $step->position.'. '.$step->action : '—',
                'finding' => $finding->description,
                'url' => route('bcm.exercises.show', $exercise),
            ]),
            $finding->severity->value,
            $actor,
            $step?->configuration_item_id !== null ? [$step->configuration_item_id] : [],
        );

        $finding->forceFill(['ticket_id' => $ticket->id, 'ticket_number' => $ticket->number])->save();
        $this->trail->record(AuditEvent::Updated, $exercise, null, ['finding_ticket' => $ticket->number], $actor->id);

        return $ticket;
    }
}
