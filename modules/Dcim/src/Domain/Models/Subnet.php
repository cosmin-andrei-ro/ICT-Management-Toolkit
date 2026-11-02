<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Dcim\Database\Factories\SubnetFactory;
use Modules\Dcim\Domain\IpNetwork;
use UnexpectedValueException;

/**
 * A network; subnets inside it and the addresses in it follow from containment.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $network
 * @property ?string $gateway
 * @property ?string $vlan_id
 * @property ?string $location_id
 * @property ?string $purpose
 * @property ?string $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?Vlan $vlan
 */
#[Table('dcim_subnets')]
#[UseFactory(SubnetFactory::class)]
final class Subnet extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SubnetFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<Vlan, $this>
     */
    public function vlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class);
    }

    public function parsed(): IpNetwork
    {
        return IpNetwork::parse($this->network) ?? throw new UnexpectedValueException("Invalid network [{$this->network}].");
    }

    /**
     * Addresses inside this network, at any depth.
     *
     * @return Builder<IpAddress>
     */
    public function addresses(): Builder
    {
        return IpAddress::query()->whereRaw('address << ?::cidr', [$this->network]);
    }

    /**
     * The most specific subnet holding an address or network.
     */
    public static function holding(string $addressOrNetwork, ?string $exceptId = null): ?self
    {
        return self::query()->whereRaw('network >> ?::inet', [$addressOrNetwork])
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->orderByRaw('masklen(network) DESC')
            ->first();
    }

    /**
     * The first address nobody holds, the gateway excluded; null when none
     * among the first 65,536.
     */
    public function nextFree(): ?string
    {
        $network = $this->parsed();
        $usable = $network->usable() ?? 65536;
        $offset = $network->family === 4 && $network->prefix < 31 ? 1 : 0;

        $row = $this->getConnection()->selectOne(
            'SELECT host(n.candidate) AS address FROM (SELECT host(?::cidr::inet + s.i)::inet AS candidate FROM generate_series(?::bigint, ?::bigint) AS s(i)) n
             WHERE NOT EXISTS (SELECT 1 FROM dcim_ip_addresses a WHERE a.organization_id = ? AND a.address = n.candidate)
             AND (?::inet IS NULL OR n.candidate <> ?::inet)
             LIMIT 1',
            [$this->network, $offset, $offset + min($usable, 65536) - 1, $this->organization_id, $this->gateway, $this->gateway],
        );

        return is_object($row) && isset($row->address) && is_string($row->address) ? $row->address : null;
    }
}
