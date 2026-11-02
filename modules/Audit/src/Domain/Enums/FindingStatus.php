<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * Open while being handled, under verification once its owner says it is fixed, closed once someone else verified it. Mirrored by audit_findings_status_check.
 */
enum FindingStatus: string
{
    case Open = 'open';
    case Verification = 'verification';
    case Closed = 'closed';

    public function label(): string
    {
        return __('audit::audits.finding_statuses.'.$this->value);
    }
}
