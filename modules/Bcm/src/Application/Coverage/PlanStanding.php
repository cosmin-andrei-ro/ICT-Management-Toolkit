<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Coverage;

use Carbon\CarbonImmutable;
use Modules\Bcm\Domain\Models\Plan;

/**
 * Where an active plan stands: in force or not, review and exercise due dates,
 * and whether its runbook fits the strictest RTO of what it covers.
 */
final readonly class PlanStanding
{
    public function __construct(
        public Plan $plan,
        public ?string $versionId,
        public ?int $estimateMinutes,
        public ?int $rtoMinutes,
        public ?CarbonImmutable $exerciseDueOn,
        public bool $reviewOverdue,
        public bool $exerciseOverdue,
        public bool $awaitingApproval,
        public ?string $lastOutcome,
    ) {}

    public function inForce(): bool
    {
        return $this->versionId !== null;
    }

    /**
     * Null when either side is unknown.
     */
    public function fitsRto(): ?bool
    {
        return $this->estimateMinutes !== null && $this->rtoMinutes !== null ? $this->estimateMinutes <= $this->rtoMinutes : null;
    }
}
