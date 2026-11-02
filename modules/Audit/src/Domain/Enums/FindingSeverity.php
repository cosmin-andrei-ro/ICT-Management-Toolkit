<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * ISO/IEC 27001 practice (clause 10.2): nonconformities need a root cause and corrective action; observations and opportunities for improvement do not. Mirrored by audit_findings_severity_check.
 */
enum FindingSeverity: string
{
    case MajorNonconformity = 'major_nonconformity';
    case MinorNonconformity = 'minor_nonconformity';
    case Observation = 'observation';
    case Opportunity = 'opportunity';

    public function isNonconformity(): bool
    {
        return $this === self::MajorNonconformity || $this === self::MinorNonconformity;
    }

    public function label(): string
    {
        return __('audit::audits.severities.'.$this->value);
    }
}
