<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Models;

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
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Application\Policies\RackPolicy;
use Modules\Dcim\Database\Factories\RackFactory;

/**
 * A rack in a room, numbered in units (U) from the bottom.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $location_id
 * @property string $name
 * @property ?string $row
 * @property ?int $position
 * @property int $height_u
 * @property ?int $max_power_watts
 * @property ?string $notes
 * @property ?string $created_by
 * @property ?string $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Location $location
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Mount> $mounts
 */
#[Table('dcim_racks')]
#[UseFactory(RackFactory::class)]
#[UsePolicy(RackPolicy::class)]
final class Rack extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<RackFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    protected $attributes = [
        'height_u' => 42,
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<Mount, $this>
     */
    public function mounts(): HasMany
    {
        return $this->hasMany(Mount::class);
    }

    /**
     * Units with equipment on at least one side.
     */
    public function usedUnits(): int
    {
        $used = [];

        foreach ($this->mounts as $mount) {
            foreach ($mount->units() as $unit) {
                $used[$unit] = true;
            }
        }

        return count($used);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'height_u' => 'integer',
            'max_power_watts' => 'integer',
        ];
    }
}
