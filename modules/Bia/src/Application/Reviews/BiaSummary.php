<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Reviews;

use Carbon\CarbonImmutable;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Enums\Tier;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;

/**
 * The state of the current organization's BIA in a few numbers, for the
 * overview and the coordinators' weekly email.
 */
final class BiaSummary
{
    /**
     * @return array{active: int, by_tier: array<int, int>, never_assessed: int, overdue: int, in_progress: int, awaiting_approval: int}
     */
    public function figures(CarbonImmutable $today): array
    {
        $active = BusinessProcess::query()->where('status', 'active');

        /** @var array<int, int> $byTier */
        $byTier = [];

        foreach (Tier::cases() as $tier) {
            $byTier[$tier->value] = (clone $active)->where('tier', $tier->value)->count();
        }

        $activeIds = (clone $active)->select('id');

        return [
            'active' => (clone $active)->count(),
            'by_tier' => $byTier,
            'never_assessed' => (clone $active)->whereNull('approved_at')->count(),
            'overdue' => (clone $active)->whereNotNull('next_review_on')->where('next_review_on', '<', $today->toDateString())->count(),
            'in_progress' => Assessment::query()->whereIn('process_id', $activeIds)->whereIn('status', [AssessmentStatus::Draft->value, AssessmentStatus::Returned->value])->count(),
            'awaiting_approval' => Assessment::query()->whereIn('process_id', $activeIds)->where('status', AssessmentStatus::Submitted->value)->count(),
        ];
    }
}
