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
use Modules\Dcim\Database\Factories\IpAddressFactory;
use Modules\Dcim\Domain\Enums\AddressStatus;

/**
 * An IP address, held by equipment in a rack or by a CMDB item.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $address
 * @property AddressStatus $status
 * @property ?string $dns_name
 * @property ?string $mount_id
 * @property ?string $configuration_item_id
 * @property ?string $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?Mount $mount
 */
#[Table('dcim_ip_addresses')]
#[UseFactory(IpAddressFactory::class)]
final class IpAddress extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<IpAddressFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<Mount, $this>
     */
    public function mount(): BelongsTo
    {
        return $this->belongsTo(Mount::class);
    }

    /**
     * The address without its mask ("10.0.0.5", not "10.0.0.5/32").
     */
    public function host(): string
    {
        return explode('/', $this->address)[0];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => AddressStatus::class];
    }
}
