<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Domain\Models\Project;

/**
 * Projects for the global search, only for those who may view them.
 */
final class ProjectsSearchRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Project::query()->whereKey($ids)->limit(100)->get()
            ->filter(fn (Project $record): bool => $gate->allows('view', $record))
            ->map(fn (Project $record): LinkedRecord => new LinkedRecord($record->id, $record->code.' '.$record->name, route('projects.show', $record)))
            ->all());
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function projects(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);
        $like = LinkedRecords::like($term);

        return array_values(Project::query()
            ->where(fn (Builder $match) => $match->whereLike('name', $like)->orWhereLike('code', $like))
            ->latest()->limit(20)->get()
            ->filter(fn (Project $project): bool => $gate->allows('view', $project))
            ->map(fn (Project $project): LinkedRecord => new LinkedRecord($project->id, $project->code.' '.$project->name, route('projects.show', $project)))
            ->all());
    }
}
