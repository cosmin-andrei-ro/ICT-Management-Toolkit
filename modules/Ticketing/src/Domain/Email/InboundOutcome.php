<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Email;

/**
 * What happened to a message read from a mailbox.
 */
enum InboundOutcome: string
{
    case Created = 'created';
    case Commented = 'commented';
    case IgnoredUnknownSender = 'ignored_unknown_sender';
    case IgnoredAutomatic = 'ignored_automatic';
    case IgnoredSpoofed = 'ignored_spoofed';
    case IgnoredRateLimited = 'ignored_rate_limited';
    case IgnoredTooLarge = 'ignored_too_large';
    case Failed = 'failed';

    public function label(): string
    {
        return __('ticketing::email.outcomes.'.$this->value);
    }

    public function reachedTicket(): bool
    {
        return $this === self::Created || $this === self::Commented;
    }
}
