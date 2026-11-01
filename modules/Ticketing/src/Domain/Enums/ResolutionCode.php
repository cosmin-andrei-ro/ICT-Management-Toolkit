<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Enums;

/**
 * Mirrored by a CHECK constraint; used for reporting (e.g. share of workarounds).
 */
enum ResolutionCode: string
{
    case Fixed = 'fixed';
    case Workaround = 'workaround';
    case Fulfilled = 'fulfilled';
    case NotReproducible = 'not_reproducible';
    case Duplicate = 'duplicate';
    case UserError = 'user_error';
    case NoActionNeeded = 'no_action_needed';
    case Implemented = 'implemented';
    case RolledBack = 'rolled_back';

    /**
     * Changes close as implemented or rolled back; other tickets never do.
     *
     * @return list<self>
     */
    public static function forType(TicketType $type): array
    {
        return $type === TicketType::Change
            ? [self::Implemented, self::RolledBack]
            : [self::Fixed, self::Workaround, self::Fulfilled, self::NotReproducible, self::Duplicate, self::UserError, self::NoActionNeeded];
    }

    public function label(): string
    {
        return __('ticketing::tickets.resolution_codes.'.$this->value);
    }
}
