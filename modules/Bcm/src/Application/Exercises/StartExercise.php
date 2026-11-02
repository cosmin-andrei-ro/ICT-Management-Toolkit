<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exercises;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\ExerciseItem;
use Modules\Bcm\Domain\Models\ExerciseStep;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Domain\Links\ConfigurationItems;

/**
 * Starts an exercise on the version in force at that moment: its runbook is
 * copied, and the configuration items it covers or works on are listed for
 * the team to confirm they came back.
 */
final readonly class StartExercise
{
    public function __construct(
        private ConnectionInterface $db,
        private ConfigurationItems $items,
    ) {}

    /**
     * @throws ExerciseClosed unless the exercise is planned
     * @throws InvalidArgumentException when the plan has no version in force any more
     */
    public function handle(Exercise $exercise): void
    {
        $this->db->transaction(function () use ($exercise): void {
            $locked = Exercise::query()->lockForUpdate()->findOrFail($exercise->id);

            if ($locked->status !== ExerciseStatus::Planned) {
                throw new ExerciseClosed;
            }

            $version = PlanVersion::query()->where('plan_id', $locked->plan_id)->where('status', VersionStatus::Approved)->first()
                ?? throw new InvalidArgumentException('Only a plan in force can be exercised.');

            $itemIds = $this->items->linkedIds('bcm.plan_version', [$version->id])[$version->id] ?? [];

            foreach ($version->steps()->get() as $step) {
                (new ExerciseStep)->forceFill([
                    'organization_id' => $locked->organization_id,
                    'exercise_id' => $locked->id,
                    'position' => $step->position,
                    'phase' => $step->phase,
                    'action' => $step->action,
                    'estimated_minutes' => $step->estimated_minutes,
                    'configuration_item_id' => $step->configuration_item_id,
                ])->save();

                if ($step->configuration_item_id !== null) {
                    $itemIds[] = $step->configuration_item_id;
                }
            }

            // Only items the CMDB still knows (in this organization).
            foreach (array_keys($this->items->recoveryProfiles(array_values(array_unique($itemIds)))) as $itemId) {
                (new ExerciseItem)->forceFill([
                    'organization_id' => $locked->organization_id,
                    'exercise_id' => $locked->id,
                    'configuration_item_id' => $itemId,
                ])->save();
            }

            $locked->forceFill(['status' => ExerciseStatus::InProgress, 'version_id' => $version->id, 'started_at' => now()])->save();
        });

        $exercise->refresh();
    }
}
