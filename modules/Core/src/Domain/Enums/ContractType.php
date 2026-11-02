<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

enum ContractType: string
{
    case Support = 'support';
    case License = 'license';
    case Subscription = 'subscription';
    case Lease = 'lease';
    case Purchase = 'purchase';
    case Service = 'service';
    case Other = 'other';

    public function label(): string
    {
        return __('core::contracts.types.'.$this->value);
    }
}
