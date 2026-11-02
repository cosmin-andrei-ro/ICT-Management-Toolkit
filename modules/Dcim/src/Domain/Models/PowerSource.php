<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Database\Factories\PowerSourceFactory;
use Modules\Dcim\Domain\Enums\PowerKind;
use Modules\Dcim\Domain\Enums\PowerSide;

/**
 * A feed, UPS or PDU, fed by the one above it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $location_id
 * @property PowerKind $kind
 * @property string $name
 * @property ?PowerSide $side
 * @property ?string $upstream_id
 * @property ?string $asset_id
 * @property ?string $rack_id
 * @property ?int $capacity_watts
 * @property ?string $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Location $location
 * @property-read ?PowerSource $upstream
 * @property-read ?Rack $rack
 */
#[Table('dcim_power_sources')]
#[UseFactory(PowerSourceFactory::class)]
final class PowerSource extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<PowerSourceFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<PowerSource, $this>
     */
    public function upstream(): BelongsTo
    {
        return $this->belongsTo(self::class, 'upstream_id');
    }

    /**
     * @return HasMany<PowerSource, $this>
     */
    public function downstream(): HasMany
    {
        return $this->hasMany(self::class, 'upstream_id');
    }

    /**
     * @return HasMany<PowerConnection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(PowerConnection::class, 'source_id');
    }

    /**
     * @return BelongsTo<Rack, $this>
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PowerKind::class,
            'side' => PowerSide::class,
            'capacity_watts' => 'integer',
        ];
    }
}
