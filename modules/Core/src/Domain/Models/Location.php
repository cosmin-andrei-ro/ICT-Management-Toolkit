<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\LocationPolicy;
use Modules\Core\Database\Factories\LocationFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Enums\LocationKind;

/**
 * A place: site, building, floor or room, nested. Where assets are, and later
 * where racks stand (DCIM) and which sites a continuity plan covers (BCM).
 *
 * @property string $id
 * @property string $organization_id
 * @property ?string $parent_id
 * @property string $name
 * @property LocationKind $kind
 * @property ?string $code
 * @property ?string $address
 * @property ?string $notes
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Location $parent
 */
#[Table('core_locations')]
#[UseFactory(LocationFactory::class)]
#[UsePolicy(LocationPolicy::class)]
#[Fillable(['parent_id', 'name', 'kind', 'code', 'address', 'notes', 'is_active'])]
final class Location extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => 'site',
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Every location of the current organization as "Site › Building › Room", by id,
     * sorted by that path. Locations are few, so one query beats walking parents.
     *
     * @return array<string, string>
     */
    public static function paths(bool $activeOnly = true): array
    {
        $all = self::query()->get(['id', 'parent_id', 'name', 'is_active'])->keyBy('id');
        $paths = [];

        foreach ($all as $location) {
            if ($activeOnly && ! $location->is_active) {
                continue;
            }

            $names = [];
            $seen = [];

            for ($node = $location; $node !== null && ! isset($seen[$node->id]); $node = $node->parent_id !== null ? $all->get($node->parent_id) : null) {
                $seen[$node->id] = true;
                array_unshift($names, $node->name);
            }

            $paths[$location->id] = implode(' › ', $names);
        }

        asort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    /**
     * Ids of this location and everything under it: none of them can become its parent.
     *
     * @return list<string>
     */
    public function selfAndDescendantIds(): array
    {
        $children = self::query()->whereNotNull('parent_id')->pluck('parent_id', 'id')->all();
        $ids = [$this->id];

        for ($added = true; $added;) {
            $added = false;

            foreach ($children as $id => $parentId) {
                if (in_array($parentId, $ids, true) && ! in_array($id, $ids, true)) {
                    $ids[] = (string) $id;
                    $added = true;
                }
            }
        }

        return $ids;
    }

    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            'is_active' => 'boolean',
        ];
    }
}
