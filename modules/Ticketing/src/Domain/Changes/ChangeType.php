<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Changes;

/**
 * Mirrored by ticketing_change_details_type_check.
 */
enum ChangeType: string
{
    /** Routine, low-risk, from a pre-approved template: no CAB. */
    case Standard = 'standard';
    case Normal = 'normal';
    /** Urgent; authorized by an emergency CAB (ECAB), reviewed after implementation. */
    case Emergency = 'emergency';

    public function needsCab(): bool
    {
        return $this !== self::Standard;
    }

    public function label(): string
    {
        return __('ticketing::changes.types.'.$this->value);
    }
}
