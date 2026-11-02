<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exercises;

use InvalidArgumentException;
use Modules\Bcm\Domain\Enums\ExerciseType;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

final readonly class PlanExercise
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @param  array{type: string, scenario?: ?string, title: string, scheduled_on: string, facilitator_id?: ?string, participants?: ?string}  $data
     *
     * @throws InvalidArgumentException when the plan has no version in force, or the facilitator is not a member
     */
    public function handle(Plan $plan, array $data): Exercise
    {
        if ($plan->approved_version === null) {
            throw new InvalidArgumentException('Only a plan in force can be exercised.');
        }

        $facilitatorId = trim((string) ($data['facilitator_id'] ?? '')) ?: null;

        if ($facilitatorId !== null && ! User::query()->whereKey($facilitatorId)->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))->exists()) {
            throw new InvalidArgumentException('The facilitator must belong to the organization.');
        }

        $exercise = new Exercise;
        $exercise->forceFill([
            'organization_id' => $plan->organization_id,
            'plan_id' => $plan->id,
            'type' => ExerciseType::from($data['type']),
            'scenario' => ($data['scenario'] ?? '') !== '' ? Scenario::from((string) $data['scenario']) : null,
            'title' => trim($data['title']),
            'scheduled_on' => $data['scheduled_on'],
            'facilitator_id' => $facilitatorId,
            'participants' => trim((string) ($data['participants'] ?? '')) ?: null,
        ])->save();

        return $exercise;
    }
}
