<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exercises;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Bcm\Application\PlanTiming;
use Modules\Bcm\Domain\Enums\ExerciseOutcome;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\StepResult;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\ExerciseItem;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;

/**
 * Closes an exercise as evidence: every step needs a result. The measured
 * recovery time is kept against the strictest RTO of the moment, the plan
 * records when it was last exercised, and the items that came back are
 * confirmed in the CMDB as recovered by this test.
 */
final readonly class CompleteExercise
{
    public function __construct(
        private ConnectionInterface $db,
        private PlanTiming $timing,
        private ConfigurationItems $items,
    ) {}

    /**
     * @throws ExerciseClosed unless the exercise is in progress
     * @throws ValidationException when a step has no result (key "steps")
     */
    public function handle(Exercise $exercise, ExerciseOutcome $outcome, ?string $summary, ?string $lessons, User $actor): void
    {
        $this->db->transaction(function () use ($exercise, $outcome, $summary, $lessons, $actor): void {
            $locked = Exercise::query()->with(['plan', 'version'])->lockForUpdate()->findOrFail($exercise->id);

            if ($locked->status !== ExerciseStatus::InProgress || $locked->version === null) {
                throw new ExerciseClosed;
            }

            if ($locked->steps()->where('result', StepResult::Pending)->exists()) {
                throw ValidationException::withMessages(['steps' => __('bcm::exercises.errors.pending_steps')]);
            }

            $today = now()->toImmutable()->startOfDay();
            $measured = $locked->measuredRecoveryMinutes();

            $locked->forceFill([
                'status' => ExerciseStatus::Completed,
                'outcome' => $outcome,
                'completed_at' => now(),
                'measured_minutes' => $measured,
                'rto_minutes' => $this->timing->strictestRto($locked->version)?->rtoMinutes,
                'summary' => self::blankToNull($summary),
                'lessons' => self::blankToNull($lessons),
            ])->save();

            $plan = Plan::query()->lockForUpdate()->findOrFail($locked->plan_id);

            if ($plan->last_exercised_on === null || $plan->last_exercised_on->lt($today)) {
                $plan->forceFill(['last_exercised_on' => $today])->save();
            }

            $evidence = __('bcm::exercises.evidence', ['plan' => $plan->name, 'title' => $locked->title, 'date' => $today->toDateString()]);

            foreach (ExerciseItem::query()->where('exercise_id', $locked->id)->where('recovered', true)->get() as $item) {
                $this->items->confirmRecovery($item->configuration_item_id, $item->measured_minutes, $today, $evidence, $actor);
            }
        });

        $exercise->refresh();
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
