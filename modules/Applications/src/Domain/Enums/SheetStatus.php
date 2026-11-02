<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Enums;

/**
 * Mirrored by applications_sheets_status_check.
 */
enum SheetStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Superseded = 'superseded';

    /**
     * Its content can still change.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Submitted || $this === self::Returned;
    }

    public function label(): string
    {
        return __('applications::applications.statuses.'.$this->value);
    }
}
