<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Itam\Application\Policies\LicensePolicy;
use Modules\Itam\Database\Factories\SoftwareProductFactory;

/**
 * A piece of software licences are bought for: "Office Suite E3", "Design Suite".
 * Compliance is counted per product, across its licences.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ?string $publisher
 * @property ?string $notes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('itam_software_products')]
#[UseFactory(SoftwareProductFactory::class)]
#[UsePolicy(LicensePolicy::class)]
#[Fillable(['name', 'publisher', 'notes'])]
final class SoftwareProduct extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SoftwareProductFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class, 'product_id');
    }
}
