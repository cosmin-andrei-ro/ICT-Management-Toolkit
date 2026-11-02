<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * Where a requirement stands, from its applicability and the controls that
 * cover it (never stored: it follows the controls).
 */
enum RequirementState: string
{
    case Undecided = 'undecided';
    case NotApplicable = 'not_applicable';
    case NotCovered = 'not_covered';
    case Planned = 'planned';
    case Partial = 'partial';
    case Implemented = 'implemented';

    /**
     * @param  list<ControlStatus>  $controls  Statuses of the controls mapped (retired ones left out).
     */
    public static function of(?bool $applicable, array $controls): self
    {
        $active = array_values(array_filter($controls, fn (ControlStatus $status): bool => $status !== ControlStatus::Retired));

        return match (true) {
            $applicable === false => self::NotApplicable,
            $active === [] => $applicable === null ? self::Undecided : self::NotCovered,
            in_array(ControlStatus::Implemented, $active, true) && ! in_array(ControlStatus::Planned, $active, true) && ! in_array(ControlStatus::Partial, $active, true) => self::Implemented,
            in_array(ControlStatus::Implemented, $active, true) || in_array(ControlStatus::Partial, $active, true) => self::Partial,
            default => self::Planned,
        };
    }

    public function label(): string
    {
        return __('audit::compliance.requirement_states.'.$this->value);
    }
}
