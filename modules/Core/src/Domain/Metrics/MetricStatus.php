<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Metrics;

/**
 * How a value reads against its thresholds; Neutral when it has none.
 */
enum MetricStatus: string
{
    case Good = 'good';
    case Warning = 'warning';
    case Alert = 'alert';
    case Neutral = 'neutral';
}
