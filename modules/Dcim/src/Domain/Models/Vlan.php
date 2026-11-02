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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Dcim\Database\Factories\VlanFactory;

/**
 * A VLAN, for a site or everywhere.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $vid
 * @property string $name
 * @property ?string $location_id
 * @property ?string $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('dcim_vlans')]
#[UseFactory(VlanFactory::class)]
final class Vlan extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<VlanFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /**
     * @return HasMany<Subnet, $this>
     */
    public function subnets(): HasMany
    {
        return $this->hasMany(Subnet::class);
    }

    public function label(): string
    {
        return $this->vid.' '.$this->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['vid' => 'integer'];
    }
}
