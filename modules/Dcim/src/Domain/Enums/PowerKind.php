<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Enums;

/**
 * A link of the power chain: the utility feed or generator, a UPS, a PDU.
 */
enum PowerKind: string
{
    case Feed = 'feed';
    case Ups = 'ups';
    case Pdu = 'pdu';

    public function label(): string
    {
        return __('dcim::power.kinds.'.$this->value);
    }
}
