<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Enums;

/**
 * Derived from impact × urgency (PriorityMatrix). Mirrored by a CHECK constraint.
 */
enum Priority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return __('ticketing::tickets.priorities.'.$this->value);
    }

    /**
     * For ordering: lower is more urgent.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 1,
            self::High => 2,
            self::Medium => 3,
            self::Low => 4,
        };
    }
}
