<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Enums;

/**
 * How an address is used: in use by a host, kept for later, or handed out by DHCP.
 */
enum AddressStatus: string
{
    case Active = 'active';
    case Reserved = 'reserved';
    case Dhcp = 'dhcp';

    public function label(): string
    {
        return __('dcim::ipam.statuses.'.$this->value);
    }
}
