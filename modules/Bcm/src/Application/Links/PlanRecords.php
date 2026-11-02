<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * Continuity and recovery plans, as other modules link to them (LinkedRecords): only those the viewer may see.
 */
final class PlanRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        return self::records(Plan::query()->whereKey($ids)->limit(100)->get(), $viewer);
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        $like = LinkedRecords::like($term);

        return self::records(Plan::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('name', $like)))
            ->latest()
            ->limit(50)
            ->get(), $viewer);
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @return list<LinkedRecord>
     */
    private static function records(Collection $plans, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values($plans
            ->filter(fn (Plan $plan): bool => $gate->allows('view', $plan))
            ->map(fn (Plan $plan): LinkedRecord => new LinkedRecord(
                $plan->id,
                __('bcm::plans.linked', ['type' => $plan->type->label(), 'plan' => $plan->name]),
                route('bcm.plans.show', $plan),
                $plan->approved_version !== null ? 'v'.$plan->approved_version : null,
            ))
            ->all());
    }
}
