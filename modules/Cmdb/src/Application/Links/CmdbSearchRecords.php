<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Links;

use Illuminate\Support\Facades\Gate;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Models\User;

/**
 * Configuration items for the global search, only for those who may view them.
 */
final class CmdbSearchRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(ConfigurationItem::query()->whereKey($ids)->limit(100)->get()
            ->filter(fn (ConfigurationItem $record): bool => $gate->allows('view', $record))
            ->map(fn (ConfigurationItem $record): LinkedRecord => new LinkedRecord($record->id, $record->name, route('cmdb.items.show', $record)))
            ->all());
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function items(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        if (! $gate->allows('viewAny', ConfigurationItem::class)) {
            return [];
        }

        return array_values(ConfigurationItem::query()->search($term)->orderBy('name')->limit(20)->get()
            ->filter(fn (ConfigurationItem $item): bool => $gate->allows('view', $item))
            ->map(fn (ConfigurationItem $item): LinkedRecord => new LinkedRecord($item->id, $item->name, route('cmdb.items.show', $item), $item->identifier))
            ->all());
    }
}
