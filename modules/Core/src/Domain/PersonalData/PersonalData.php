<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\PersonalData;

use Closure;
use Modules\Core\Domain\Models\User;

/**
 * What each module holds about a person, for the export of their personal
 * data (ADR 0058). Modules register a collector, so Core never reads their
 * tables; collectors run in the organization being exported and return the
 * records the person is named in.
 */
final class PersonalData
{
    /** @var array<string, Closure(User): iterable<PersonalDataEntry>> */
    private array $collectors = [];

    /**
     * @param  string  $label  Translation key of the module's name: the sheet of the export.
     * @param  Closure(User): iterable<PersonalDataEntry>  $collector
     */
    public function register(string $label, Closure $collector): void
    {
        $this->collectors[$label] = $collector;
    }

    /**
     * @return array<string, Closure(User): iterable<PersonalDataEntry>> By label, in registration order.
     */
    public function collectors(): array
    {
        return $this->collectors;
    }
}
