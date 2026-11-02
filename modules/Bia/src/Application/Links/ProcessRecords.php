<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * Business processes of the impact analysis, as other modules link to them (LinkedRecords): only those the viewer may see.
 */
final class ProcessRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        return self::records(BusinessProcess::query()->whereKey($ids)->limit(100)->get(), $viewer);
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        $like = LinkedRecords::like($term);

        return self::records(BusinessProcess::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('name', $like)))
            ->latest()
            ->limit(50)
            ->get(), $viewer);
    }

    /**
     * @param  Collection<int, BusinessProcess>  $processs
     * @return list<LinkedRecord>
     */
    private static function records(Collection $processs, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values($processs
            ->filter(fn (BusinessProcess $process): bool => $gate->allows('view', $process))
            ->map(fn (BusinessProcess $process): LinkedRecord => new LinkedRecord($process->id, $process->name, route('bia.processes.show', $process), $process->tier?->label()))
            ->all());
    }
}
