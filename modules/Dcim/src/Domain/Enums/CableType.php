<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Enums;

/**
 * Cable types: copper categories, fibre grades, direct-attach.
 */
enum CableType: string
{
    case Cat5e = 'cat5e';
    case Cat6 = 'cat6';
    case Cat6a = 'cat6a';
    case Cat7 = 'cat7';
    case Om3 = 'om3';
    case Om4 = 'om4';
    case Os2 = 'os2';
    case Dac = 'dac';
    case Aoc = 'aoc';
    case Console = 'console';
    case Other = 'other';

    public function label(): string
    {
        return __('dcim::cabling.cable_types.'.$this->value);
    }
}
