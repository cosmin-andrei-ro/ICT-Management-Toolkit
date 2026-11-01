<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Sla;

final readonly class SlaTarget
{
    public function __construct(
        public int $responseMinutes,
        public int $resolutionMinutes,
    ) {}

    public function minutesFor(SlaMetric $metric): int
    {
        return $metric === SlaMetric::Response ? $this->responseMinutes : $this->resolutionMinutes;
    }
}
