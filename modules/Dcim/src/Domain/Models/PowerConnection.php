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
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Dcim\Database\Factories\PowerConnectionFactory;

/**
 * Mounted equipment plugged into a PDU (or straight into a UPS).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $mount_id
 * @property string $source_id
 * @property ?string $outlet
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Mount $mount
 * @property-read PowerSource $source
 */
#[Table('dcim_power_connections')]
#[UseFactory(PowerConnectionFactory::class)]
final class PowerConnection extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<PowerConnectionFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<Mount, $this>
     */
    public function mount(): BelongsTo
    {
        return $this->belongsTo(Mount::class);
    }

    /**
     * @return BelongsTo<PowerSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(PowerSource::class);
    }
}
