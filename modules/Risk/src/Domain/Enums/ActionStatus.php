<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * Mirrored by risk_actions_status_check.
 */
enum ActionStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Planned || $this === self::InProgress;
    }

    public function label(): string
    {
        return __('risk::treatment.action_statuses.'.$this->value);
    }
}
