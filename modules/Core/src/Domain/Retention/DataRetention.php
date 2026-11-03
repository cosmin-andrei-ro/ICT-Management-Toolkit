<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Retention;

use Carbon\CarbonImmutable;
use Closure;
use InvalidArgumentException;

/**
 * How long each kind of data is kept (ADR 0060). Modules register a policy
 * with a default period and what to do with what is older - delete or
 * anonymize it - and each organization may change the period or keep forever.
 */
final class DataRetention
{
    /** @var array<string, array{label: string, help: string, default: int, apply: Closure(string, CarbonImmutable): int}> */
    private array $policies = [];

    /**
     * @param  string  $key  E.g. "ticketing.closed_tickets".
     * @param  string  $label  Translation key.
     * @param  string  $help  Translation key: what happens to what is older.
     * @param  int  $defaultYears  Used until the organization chooses.
     * @param  Closure(string, CarbonImmutable): int  $apply  Given the organization and the cut-off, returns how many records it handled.
     */
    public function register(string $key, string $label, string $help, int $defaultYears, Closure $apply): void
    {
        if ($defaultYears < 1) {
            throw new InvalidArgumentException('A retention period is at least one year.');
        }

        $this->policies[$key] = ['label' => $label, 'help' => $help, 'default' => $defaultYears, 'apply' => $apply];
    }

    /**
     * @return array<string, array{label: string, help: string, default: int, apply: Closure(string, CarbonImmutable): int}>
     */
    public function policies(): array
    {
        return $this->policies;
    }
}
