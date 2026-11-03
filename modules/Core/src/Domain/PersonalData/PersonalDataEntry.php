<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\PersonalData;

use BackedEnum;
use DateTimeInterface;

/**
 * One record a person is part of, as the export of their personal data lists
 * it (GDPR Art. 15 and 20, ADR 0058): what it is, how they are involved, and
 * the personal content it holds (the text they wrote, for instance).
 */
final readonly class PersonalDataEntry
{
    public function __construct(
        public string $record,
        public ?string $reference,
        public string $title,
        public string $role,
        public ?DateTimeInterface $date = null,
        public ?string $details = null,
    ) {}

    /**
     * An enum as people read it (its label when it has one), or a plain value.
     */
    public static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum && method_exists($value, 'label') && is_string($label = $value->label()) => $label,
            $value instanceof BackedEnum => (string) $value->value,
            is_scalar($value) => (string) $value,
            default => null,
        };
    }

    /**
     * The person's parts in a record, those that apply: "Owner, Submitted it".
     */
    public static function roles(mixed ...$roles): string
    {
        return implode(', ', array_filter($roles, is_string(...)));
    }
}
