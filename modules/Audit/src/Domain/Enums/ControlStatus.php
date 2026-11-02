<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * How far a control is implemented. Mirrored by audit_controls_status_check.
 */
enum ControlStatus: string
{
    case Planned = 'planned';
    case Partial = 'partial';
    case Implemented = 'implemented';
    case Retired = 'retired';

    public function label(): string
    {
        return __('audit::compliance.control_statuses.'.$this->value);
    }
}
