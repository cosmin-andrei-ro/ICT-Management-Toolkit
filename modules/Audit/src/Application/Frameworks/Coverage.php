<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Frameworks;

use Modules\Audit\Domain\Enums\RequirementState;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;

/**
 * How far a framework is met: requirements by state, and the share of the
 * applicable ones that are implemented.
 */
final class Coverage
{
    /**
     * @return array{total: int, states: array<string, int>, applicable: int, implemented_percent: ?int}
     */
    public function of(Framework $framework): array
    {
        $states = array_fill_keys(array_map(fn (RequirementState $state): string => $state->value, RequirementState::cases()), 0);
        $requirements = Requirement::query()->with('controls')->where('framework_id', $framework->id)->get();

        foreach ($requirements as $requirement) {
            $states[$requirement->state()->value]++;
        }

        $applicable = $requirements->count() - $states[RequirementState::NotApplicable->value];

        return [
            'total' => $requirements->count(),
            'states' => $states,
            'applicable' => $applicable,
            'implemented_percent' => $applicable > 0 ? (int) round(100 * $states[RequirementState::Implemented->value] / $applicable) : null,
        ];
    }
}
