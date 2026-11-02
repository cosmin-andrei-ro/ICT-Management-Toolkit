<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Enums;

/**
 * Open until a risk manager turns it into a risk or dismisses it; resolved when
 * the situation that suggested it is gone. Mirrored by risk_suggestions_status_check.
 */
enum SuggestionStatus: string
{
    case Open = 'open';
    case Converted = 'converted';
    case Dismissed = 'dismissed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return __('risk::suggestions.statuses.'.$this->value);
    }
}
