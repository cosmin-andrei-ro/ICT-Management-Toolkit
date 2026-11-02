<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Enums;

/**
 * The A or B path of a dual power supply.
 */
enum PowerSide: string
{
    case A = 'a';
    case B = 'b';

    public function label(): string
    {
        return __('dcim::power.sides.'.$this->value);
    }
}
