<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Enums;

/**
 * Where a ticket came from. Mirrored by a CHECK constraint.
 */
enum TicketChannel: string
{
    case Portal = 'portal';
    case Agent = 'agent';
    case Email = 'email';
    case Api = 'api';
    case Phone = 'phone';
    case Monitoring = 'monitoring';

    public function label(): string
    {
        return __('ticketing::tickets.channels.'.$this->value);
    }
}
