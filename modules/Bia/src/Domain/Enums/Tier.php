<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Enums;

/**
 * Recovery tier from the RTO: tier 1 back within 4 hours, tier 2 within a day,
 * tier 3 within three days, tier 4 later. Common bands, so IT can plan by tier.
 */
enum Tier: int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Four = 4;

    public static function fromRto(int $minutes): self
    {
        return match (true) {
            $minutes <= 4 * 60 => self::One,
            $minutes <= 24 * 60 => self::Two,
            $minutes <= 72 * 60 => self::Three,
            default => self::Four,
        };
    }

    public function label(): string
    {
        return __('bia::processes.tiers.'.$this->value);
    }
}
