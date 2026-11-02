<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Enums;

/**
 * Who approves a major version, as in the template's approvals table.
 * Mirrored by applications_sheet_approvals_role_check.
 */
enum ApprovalRole: string
{
    case Business = 'business';
    case Technical = 'technical';
    case Security = 'security';

    public function label(): string
    {
        return __('applications::applications.approval_roles.'.$this->value);
    }
}
