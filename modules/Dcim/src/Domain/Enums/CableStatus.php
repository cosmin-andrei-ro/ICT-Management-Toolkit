<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Enums;

/**
 * A cable in place, or one planned (to be laid).
 */
enum CableStatus: string
{
    case Connected = 'connected';
    case Planned = 'planned';

    public function label(): string
    {
        return __('dcim::cabling.cable_statuses.'.$this->value);
    }
}
