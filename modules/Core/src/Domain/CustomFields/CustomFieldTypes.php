<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\CustomFields;

/**
 * The kinds of record that can have custom fields (ADR 0077). Each module registers
 * its own, so a disabled module's kinds do not appear.
 */
final class CustomFieldTypes
{
    /** @var array<string, string> */
    private array $types = [];

    /**
     * @param  string  $label  Translation key naming the kind of record.
     */
    public function register(string $morphType, string $label): void
    {
        $this->types[$morphType] = $label;
    }

    /**
     * @return array<string, string> Morph type => translated label, by label.
     */
    public function all(): array
    {
        $labels = array_map(fn (string $label): string => __($label), $this->types);
        asort($labels);

        return $labels;
    }
}
