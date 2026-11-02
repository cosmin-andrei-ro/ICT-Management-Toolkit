<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * Mirrored by audit_audits_kind_check.
 */
enum AuditKind: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return __('audit::audits.kinds.'.$this->value);
    }
}
