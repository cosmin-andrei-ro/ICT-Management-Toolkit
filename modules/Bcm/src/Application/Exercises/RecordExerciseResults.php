<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exercises;

use Illuminate\Database\ConnectionInterface;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\StepResult;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\ExerciseItem;
use Modules\Bcm\Domain\Models\ExerciseStep;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;

/**
 * What happened during an exercise in progress: the result and time of each
 * step, and whether each configuration item came back. Changes are audited on
 * the exercise.
 */
final readonly class RecordExerciseResults
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array<string, array{result: string, actual_minutes: ?int, notes: ?string}>  $steps  By exercise step id.
     * @param  array<string, array{recovered: ?bool, measured_minutes: ?int, notes: ?string}>  $items  By exercise item id.
     *
     * @throws ExerciseClosed unless the exercise is in progress
     */
    public function handle(Exercise $exercise, array $steps, array $items, User $actor): void
    {
        if ($exercise->status !== ExerciseStatus::InProgress) {
            throw new ExerciseClosed;
        }

        $changes = [];

        $this->db->transaction(function () use ($exercise, $steps, $items, &$changes): void {
            foreach (ExerciseStep::query()->where('exercise_id', $exercise->id)->whereKey(array_keys($steps))->get() as $step) {
                $step->forceFill([
                    'result' => StepResult::from($steps[$step->id]['result']),
                    'actual_minutes' => $steps[$step->id]['actual_minutes'],
                    'notes' => self::blankToNull($steps[$step->id]['notes']),
                ]);

                if ($step->isDirty()) {
                    $step->save();
                    $changes['step_'.$step->position] = $step->result->value.($step->actual_minutes !== null ? ' ('.$step->actual_minutes.' min)' : '');
                }
            }

            foreach (ExerciseItem::query()->where('exercise_id', $exercise->id)->whereKey(array_keys($items))->get() as $item) {
                $item->forceFill([
                    'recovered' => $items[$item->id]['recovered'],
                    'measured_minutes' => $items[$item->id]['measured_minutes'],
                    'notes' => self::blankToNull($items[$item->id]['notes']),
                ]);

                if ($item->isDirty()) {
                    $item->save();
                    $changes['item_'.$item->configuration_item_id] = match ($item->recovered) {
                        true => 'recovered',
                        false => 'not recovered',
                        null => 'not tested',
                    };
                }
            }
        });

        if ($changes !== []) {
            $this->trail->record(AuditEvent::Updated, $exercise, null, ['results' => $changes], $actor->id);
        }
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? mb_substr(trim($value), 0, 2000) : null;

        return $value === '' ? null : $value;
    }
}
