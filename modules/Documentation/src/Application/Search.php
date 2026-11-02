<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

/**
 * Full-text search of the pages a person may read (PostgreSQL, ranked: title
 * before text), with a highlighted extract.
 */
final readonly class Search
{
    public function __construct(private SpaceAccess $access) {}

    /**
     * @return LengthAwarePaginator<int, Page>
     */
    public function pages(string $terms, User $user, ?string $spaceId = null, int $perPage = 20): LengthAwarePaginator
    {
        $terms = trim(mb_substr($terms, 0, 200));
        $readable = Space::query()->tap(fn (Builder $query) => $this->access->readable($query, $user))->select('id');

        return Page::query()
            ->with('space')
            ->whereIn('space_id', $readable)
            ->when($spaceId !== null, fn (Builder $query) => $query->where('space_id', $spaceId))
            // websearch_to_tsquery accepts what people type ("quoted phrases", -excluded) without syntax errors.
            ->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$terms])
            ->select('documentation_pages.*')
            ->selectRaw("ts_headline('simple', body, websearch_to_tsquery('simple', ?), 'MaxFragments=2, MaxWords=25, MinWords=8, StartSel=[[, StopSel=]]') AS extract", [$terms])
            ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('simple', ?)) DESC", [$terms])
            ->orderBy('title')
            ->paginate($perPage);
    }
}
