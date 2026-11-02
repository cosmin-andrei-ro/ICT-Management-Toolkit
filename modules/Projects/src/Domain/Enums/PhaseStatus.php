<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Enums;

/**
 * A phase not started, under way, or finished.
 */
enum PhaseStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Done = 'done';

    public function label(): string
    {
        return __('projects::projects.phase_statuses.'.$this->value);
    }
}
