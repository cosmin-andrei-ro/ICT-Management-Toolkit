<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

/**
 * Mirrored by bcm_exercise_findings_severity_check; becomes the impact of the problem raised.
 */
enum FindingSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return __('bcm::exercises.severities.'.$this->value);
    }
}
