<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Bia\Application\CurrentMatrix;
use Modules\Bia\Application\Notifications\AssessmentUpdate;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Exceptions\AssessmentLocked;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Core\Domain\Models\User;

/**
 * Sends a complete assessment for approval and tells the people who approve.
 * Complete: every active cell of the impact matrix scored, impact never
 * shrinking with time, an RTO and an RPO.
 */
final readonly class SubmitAssessment
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @throws AssessmentLocked unless draft or returned
     * @throws ValidationException listing what is missing
     */
    public function handle(Assessment $assessment, User $actor): Assessment
    {
        $assessment = $this->db->transaction(function () use ($assessment, $actor): Assessment {
            $assessment = Assessment::query()->with('process')->lockForUpdate()->findOrFail($assessment->id);

            if (! $assessment->status->isEditable()) {
                throw new AssessmentLocked;
            }

            $matrix = CurrentMatrix::load();
            $read = $matrix->read($assessment->scoreMap());
            $errors = [];

            if ($matrix->categories->isEmpty() || $matrix->horizons->isEmpty() || ! $read->isComplete()) {
                $errors['scores'] = __('bia::assessments.errors.incomplete');
            } elseif (($decreasing = $read->decreasingCategories()) !== []) {
                $errors['scores'] = __('bia::assessments.errors.decreasing', ['categories' => $matrix->categories->whereIn('id', $decreasing)->pluck('name')->implode(', ')]);
            }

            if ($assessment->rto_minutes === null) {
                $errors['rto_minutes'] = __('bia::assessments.errors.rto_required');
            }

            if ($assessment->rpo_minutes === null) {
                $errors['rpo_minutes'] = __('bia::assessments.errors.rpo_required');
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $assessment->forceFill([
                'status' => AssessmentStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
                'decided_at' => null,
                'decided_by' => null,
                'decision_comment' => null,
            ])->save();

            return $assessment;
        });

        $notification = new AssessmentUpdate('submitted', $assessment->process->name, $assessment->version, route('bia.assessments.show', $assessment));

        User::query()->where('is_active', true)->whereHas('roles')->get()
            ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('bia.assessment.approve') && $user->id !== $actor->id && ! $assessment->process->isOwnedBy($user))
            ->each(fn (User $user) => $user->notify($notification));

        return $assessment;
    }
}
