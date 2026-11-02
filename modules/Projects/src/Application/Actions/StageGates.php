<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\Notifications\GateDecided;
use Modules\Projects\Application\Notifications\GateRequested;
use Modules\Projects\Domain\Enums\GateStatus;
use Modules\Projects\Domain\Enums\PhaseStatus;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Models\GateRequest;
use Modules\Projects\Domain\Models\Project;

/**
 * Stage gates (ADR 0053): the project manager asks to move to the next phase
 * - or to close the project after its closure phase - and the sponsor, who
 * never decides on their own request, approves or sends it back.
 */
final readonly class StageGates
{
    public function request(Project $project, User $actor, ?string $note): GateRequest
    {
        $project->loadMissing('phases');
        $current = $project->currentPhase();

        $error = match (true) {
            $project->status !== ProjectStatus::Active => 'not_active',
            $project->sponsor_id === null => 'no_sponsor',
            $project->sponsor_id === $actor->id => 'own_sponsor',
            $current === null => 'no_phase',
            $project->pendingGate() !== null => 'pending',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['gate' => __('projects::governance.errors.'.$error)]);
        }

        $next = $project->phases->firstWhere('position', $current->position + 1);

        $gate = new GateRequest;
        $gate->forceFill([
            'project_id' => $project->id,
            'from_phase_id' => $current->id,
            'to_phase_id' => $next?->id,
            'note' => $note,
            'requested_by' => $actor->id,
        ])->save();

        $sponsor = $project->sponsor;

        if ($sponsor !== null && $sponsor->canSignIn()) {
            $sponsor->notify(new GateRequested($project->code.' '.$project->name, $current->key->label(), $next?->key->label(), $actor->name, route('projects.show', $project)));
        }

        return $gate;
    }

    public function decide(GateRequest $gate, User $actor, bool $approve, ?string $note): void
    {
        $project = $gate->project;

        if ($gate->status !== GateStatus::Pending || $project->sponsor_id !== $actor->id || $gate->requested_by === $actor->id) {
            throw ValidationException::withMessages(['gate' => __('projects::governance.errors.not_yours')]);
        }

        if (! $approve && ($note === null || trim($note) === '')) {
            throw ValidationException::withMessages(['decisionNote' => __('projects::governance.errors.reason')]);
        }

        DB::transaction(function () use ($gate, $project, $actor, $approve, $note): void {
            $gate->forceFill([
                'status' => $approve ? GateStatus::Approved : GateStatus::Rejected,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            if (! $approve) {
                return;
            }

            $gate->fromPhase->forceFill(['status' => PhaseStatus::Done, 'completed_at' => now()])->save();

            if ($gate->toPhase !== null) {
                $gate->toPhase->forceFill(['status' => PhaseStatus::Active, 'started_at' => now()])->save();
            } else {
                $project->forceFill(['status' => ProjectStatus::Closed, 'closed_at' => now()])->save();
            }
        });

        $requester = $gate->requester;

        if ($requester !== null && $requester->canSignIn()) {
            $requester->notify(new GateDecided($project->code.' '.$project->name, $approve, $gate->toPhase?->key->label(), $actor->name, $note, route('projects.show', $project)));
        }
    }
}
