<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Enums;

/**
 * draft → submitted → approved, or back to the owner (returned) with a comment.
 * Approving a new version supersedes the previous one.
 */
enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return __('bia::assessments.statuses.'.$this->value);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::Returned], true);
    }
}
