<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Enums;

/**
 * The four phases of every project, in order.
 */
enum PhaseKey: string
{
    case Initiation = 'initiation';
    case Planning = 'planning';
    case Execution = 'execution';
    case Closure = 'closure';

    public function label(): string
    {
        return __('projects::projects.phases.'.$this->value);
    }

    public function position(): int
    {
        return (int) array_search($this, self::cases(), true) + 1;
    }
}
