<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application;

use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\ProcessSummary;

/**
 * How long a plan takes to bring the service back against how fast the
 * business needs it: the strictest RTO of the processes in scope.
 */
final readonly class PlanTiming
{
    public function __construct(private BusinessProcesses $processes) {}

    public function strictestRto(PlanVersion $version): ?ProcessSummary
    {
        $strictest = null;

        foreach ($this->processes->find($version->processIds()) as $process) {
            if ($process->rtoMinutes !== null && ($strictest === null || $process->rtoMinutes < $strictest->rtoMinutes)) {
                $strictest = $process;
            }
        }

        return $strictest;
    }

    /**
     * True when the runbook's activation and recovery steps take longer than the strictest RTO.
     */
    public function exceedsRto(PlanVersion $version): bool
    {
        $estimate = $version->estimatedRecoveryMinutes();
        $strictest = $this->strictestRto($version);

        return $estimate !== null && $strictest !== null && $estimate > (int) $strictest->rtoMinutes;
    }
}
