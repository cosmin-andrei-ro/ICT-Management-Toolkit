<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\User;

/**
 * Contracts with suppliers, as other modules link to them (LinkedRecords): only those the viewer may see.
 */
final class ContractRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        return self::records(Contract::query()->whereKey($ids)->limit(100)->get(), $viewer);
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        $like = LinkedRecords::like($term);

        return self::records(Contract::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('title', $like)->orWhereLike('reference', $like)))
            ->latest()
            ->limit(50)
            ->get(), $viewer);
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return list<LinkedRecord>
     */
    private static function records(Collection $contracts, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values($contracts
            ->filter(fn (Contract $contract): bool => $gate->allows('view', $contract))
            ->map(fn (Contract $contract): LinkedRecord => new LinkedRecord($contract->id, $contract->title.($contract->reference !== null ? ' ('.$contract->reference.')' : ''), route('core.contracts.show', $contract)))
            ->all());
    }
}
