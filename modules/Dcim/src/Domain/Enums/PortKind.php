<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Enums;

/**
 * Port types: copper, transceiver cages, fibre connectors, console.
 */
enum PortKind: string
{
    case Rj45 = 'rj45';
    case Sfp = 'sfp';
    case SfpPlus = 'sfp_plus';
    case Sfp28 = 'sfp28';
    case Qsfp = 'qsfp';
    case Lc = 'lc';
    case Sc = 'sc';
    case Console = 'console';
    case Other = 'other';

    public function label(): string
    {
        return __('dcim::cabling.port_kinds.'.$this->value);
    }
}
