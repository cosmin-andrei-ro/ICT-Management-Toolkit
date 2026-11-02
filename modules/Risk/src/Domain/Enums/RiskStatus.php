<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * Mirrored by risk_risks_status_check. Treatment states come with treatment plans.
 */
enum RiskStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __('risk::risks.statuses.'.$this->value);
    }
}
