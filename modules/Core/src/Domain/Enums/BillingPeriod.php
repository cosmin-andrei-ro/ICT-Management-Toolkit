<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

enum BillingPeriod: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('core::contracts.billing.'.$this->value);
    }
}
