<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Links;

use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\Tag;
use Modules\Core\Domain\Models\User;

/**
 * Tags for the global search, for those who may see tags.
 */
final class TagRecords
{
    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        if (! Gate::forUser($viewer)->allows('viewAny', Tag::class)) {
            return [];
        }

        return array_values(Tag::query()->whereLike('name', LinkedRecords::like(mb_strtolower($term)))->orderBy('name')->limit(20)->get()
            ->map(fn (Tag $tag): LinkedRecord => new LinkedRecord($tag->id, $tag->name, route('core.tags.show', $tag)))
            ->all());
    }
}
