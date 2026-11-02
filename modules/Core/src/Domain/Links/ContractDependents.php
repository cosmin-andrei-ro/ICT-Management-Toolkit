<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Closure;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\User;

/**
 * What depends on a contract in other modules (assets under a support contract,
 * licences…), so the contract page can list it without knowing those modules.
 * Each module returns only what the viewer may see.
 */
final class ContractDependents
{
    /** @var array<string, Closure(Contract, User): list<LinkedRecord>> */
    private array $resolvers = [];

    /**
     * @param  string  $label  Translation key of the section heading.
     * @param  Closure(Contract, User): list<LinkedRecord>  $resolver
     */
    public function register(string $label, Closure $resolver): void
    {
        $this->resolvers[$label] = $resolver;
    }

    /**
     * Non-empty sections only.
     *
     * @return list<array{label: string, records: non-empty-list<LinkedRecord>}>
     */
    public function of(Contract $contract, User $viewer): array
    {
        $sections = [];

        foreach ($this->resolvers as $label => $resolver) {
            $records = $resolver($contract, $viewer);

            if ($records !== []) {
                $sections[] = ['label' => (string) __($label), 'records' => $records];
            }
        }

        return $sections;
    }
}
