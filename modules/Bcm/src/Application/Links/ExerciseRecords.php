<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * Exercises and recovery tests of continuity plans, as other modules link to them (LinkedRecords): only those the viewer may see.
 */
final class ExerciseRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        return self::records(Exercise::query()->with('plan')->whereKey($ids)->limit(100)->get(), $viewer);
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        $like = LinkedRecords::like($term);

        return self::records(Exercise::query()->with('plan')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('title', $like)))
            ->latest()
            ->limit(50)
            ->get(), $viewer);
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     * @return list<LinkedRecord>
     */
    private static function records(Collection $exercises, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values($exercises
            ->filter(fn (Exercise $exercise): bool => $gate->allows('view', $exercise))
            ->map(fn (Exercise $exercise): LinkedRecord => new LinkedRecord(
                $exercise->id,
                $exercise->title.' · '.$exercise->scheduled_on->toDateString(),
                route('bcm.exercises.show', $exercise),
                $exercise->status->label(),
            ))
            ->all());
    }
}
