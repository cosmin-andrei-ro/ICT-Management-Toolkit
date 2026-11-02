<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * Mirrored by risk_risks_category_check.
 */
enum RiskCategory: string
{
    case InformationSecurity = 'information_security';
    case Availability = 'availability';
    case Compliance = 'compliance';
    case Supplier = 'supplier';
    case Operational = 'operational';
    case Project = 'project';

    public function label(): string
    {
        return __('risk::risks.categories.'.$this->value);
    }
}
