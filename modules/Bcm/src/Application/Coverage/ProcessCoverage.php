<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Coverage;

use Modules\Core\Domain\Links\ProcessSummary;

/**
 * An assessed business process and the plans in force that cover it.
 */
final readonly class ProcessCoverage
{
    /**
     * @param  list<PlanStanding>  $plans
     */
    public function __construct(
        public ProcessSummary $process,
        public array $plans,
    ) {}

    public function isCovered(): bool
    {
        return $this->plans !== [];
    }

    /**
     * Some plan covering it was exercised (and is not overdue).
     */
    public function isTested(): bool
    {
        foreach ($this->plans as $standing) {
            if ($standing->plan->last_exercised_on !== null && ! $standing->exerciseOverdue) {
                return true;
            }
        }

        return false;
    }

    /**
     * Some plan covering it brings it back within its RTO, as estimated.
     */
    public function fitsRto(): ?bool
    {
        if ($this->process->rtoMinutes === null || $this->plans === []) {
            return null;
        }

        $known = array_filter($this->plans, fn (PlanStanding $standing): bool => $standing->estimateMinutes !== null);

        if ($known === []) {
            return null;
        }

        return min(array_map(fn (PlanStanding $standing): int => (int) $standing->estimateMinutes, $known)) <= $this->process->rtoMinutes;
    }
}
