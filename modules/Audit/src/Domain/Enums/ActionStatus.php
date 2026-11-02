<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * Status of a corrective action. Mirrored by audit_finding_actions_status_check.
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
        return __('audit::audits.action_statuses.'.$this->value);
    }
}
