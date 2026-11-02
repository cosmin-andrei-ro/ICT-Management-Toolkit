<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

/**
 * Disruptions a plan prepares for (ISO 22301 §8.3: strategies by type of loss).
 */
enum Scenario: string
{
    case SiteLoss = 'site_loss';
    case ItOutage = 'it_outage';
    case CyberAttack = 'cyber_attack';
    case SupplierFailure = 'supplier_failure';
    case StaffUnavailable = 'staff_unavailable';
    case UtilityFailure = 'utility_failure';

    public function label(): string
    {
        return __('bcm::plans.scenarios.'.$this->value);
    }
}
