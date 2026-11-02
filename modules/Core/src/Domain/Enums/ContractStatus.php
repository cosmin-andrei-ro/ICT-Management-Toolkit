<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

/**
 * Derived from the dates, never stored: it changes with time alone.
 */
enum ContractStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    /** The deadline (end date minus notice period) is near: someone must decide. */
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Terminated = 'terminated';

    public function label(): string
    {
        return __('core::contracts.statuses.'.$this->value);
    }
}
