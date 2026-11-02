<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * Mirrored by risk_acceptances_status_check. Lapsed: the residual risk changed after approval.
 */
enum AcceptanceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Lapsed = 'lapsed';

    public function label(): string
    {
        return __('risk::treatment.acceptance_statuses.'.$this->value);
    }
}
