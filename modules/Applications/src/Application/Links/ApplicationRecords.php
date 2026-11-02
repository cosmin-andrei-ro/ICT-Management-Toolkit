<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Applications\Domain\Models\Application;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;

/**
 * Applications of the portfolio, as other modules link to them (LinkedRecords): only those the viewer may see.
 */
final class ApplicationRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        return self::records(Application::query()->whereKey($ids)->limit(100)->get(), $viewer);
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        $like = LinkedRecords::like($term);

        return self::records(Application::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('name', $like)->orWhereLike('code', $like)))
            ->latest()
            ->limit(50)
            ->get(), $viewer);
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return list<LinkedRecord>
     */
    private static function records(Collection $applications, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values($applications
            ->filter(fn (Application $application): bool => $gate->allows('view', $application))
            ->map(fn (Application $application): LinkedRecord => new LinkedRecord(
                $application->id,
                __('applications::applications.linked', ['application' => $application->name, 'code' => $application->code]),
                route('applications.show', $application),
                $application->current_version !== null ? 'v'.$application->current_version : null,
            ))
            ->all());
    }
}
