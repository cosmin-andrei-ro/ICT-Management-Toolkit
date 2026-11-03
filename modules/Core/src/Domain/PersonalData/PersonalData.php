<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\PersonalData;

use Closure;
use Modules\Core\Domain\Models\User;

/**
 * What each module holds about a person: for the export of their personal
 * data (ADR 0058) and for their anonymization (ADR 0059). Modules register a
 * collector and, where needed, an eraser, so Core never reads their tables;
 * both run in the organization concerned.
 */
final class PersonalData
{
    /** @var array<string, Closure(User): iterable<PersonalDataEntry>> */
    private array $collectors = [];

    /** @var list<Closure(User, string): void> */
    private array $erasers = [];

    /**
     * @param  string  $label  Translation key of the module's name: the sheet of the export.
     * @param  Closure(User): iterable<PersonalDataEntry>  $collector
     */
    public function register(string $label, Closure $collector): void
    {
        $this->collectors[$label] = $collector;
    }

    /**
     * What a module removes when a person is anonymized (ADR 0059): data that is
     * only personal, such as the sender address of received e-mails. Records the
     * person is part of keep their link to the account, which loses its name.
     *
     * @param  Closure(User, string): void  $eraser  Given the person and their e-mail address before anonymization.
     */
    public function registerEraser(Closure $eraser): void
    {
        $this->erasers[] = $eraser;
    }

    /**
     * @return list<Closure(User, string): void>
     */
    public function erasers(): array
    {
        return $this->erasers;
    }

    /**
     * @return array<string, Closure(User): iterable<PersonalDataEntry>> By label, in registration order.
     */
    public function collectors(): array
    {
        return $this->collectors;
    }
}
