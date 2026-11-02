<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

/**
 * Runbook phases. Activation and recovery count towards the time to bring the
 * service back, compared with the RTO; resumption and restoration follow.
 */
enum Phase: string
{
    case Activation = 'activation';
    case Recovery = 'recovery';
    case Resumption = 'resumption';
    case Restoration = 'restoration';

    public function label(): string
    {
        return __('bcm::plans.phases.'.$this->value);
    }

    public function countsTowardsRecovery(): bool
    {
        return $this === self::Activation || $this === self::Recovery;
    }
}
