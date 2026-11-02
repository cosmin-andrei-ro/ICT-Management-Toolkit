<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

enum PlanType: string
{
    /** How the business keeps working: people, places, manual workarounds. */
    case Bcp = 'bcp';

    /** How IT brings systems back. */
    case Drp = 'drp';

    public function label(): string
    {
        return __('bcm::plans.types.'.$this->value);
    }
}
