<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Http\Api\ApiQuery;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;

/**
 * The data centre, read-only (ADR 0065): racks and what is mounted in them,
 * power sources and their load, subnets and their addresses.
 */
final readonly class InfrastructureController
{
    public function __construct(private Gate $gate) {}

    /**
     * List racks. Filters: `filter[location_id]`, `filter[search]` (name). Sort: `name`.
     */
    public function racks(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Rack::class);

        return ApiQuery::collection(ApiQuery::paginate($request, Rack::query()->with('location:id,name')->withCount('mounts'), [
            'location_id' => fn (Builder $query, string $value) => $query->where('location_id', Str::isUuid($value) ? $value : null),
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', self::like($value)),
        ], ['name' => 'name'], 'name'), fn (Rack $rack): array => [
            'id' => $rack->id, 'name' => $rack->name, 'location' => ['id' => $rack->location->id, 'name' => $rack->location->name], 'row' => $rack->row, 'position' => $rack->position,
            'height_u' => $rack->height_u, 'used_u' => $rack->usedUnits(), 'max_power_watts' => $rack->max_power_watts, 'mounts_count' => $rack->getAttribute('mounts_count'),
        ]);
    }

    /**
     * Show a rack and its equipment, top to bottom.
     */
    public function rack(Rack $rack, PhysicalAssets $assets): JsonResponse
    {
        $this->gate->authorize('view', $rack);
        $mounts = $rack->mounts()->orderByDesc('position_u')->get();
        $found = $assets->find(array_values(array_filter($mounts->pluck('asset_id')->all(), is_string(...))));

        return response()->json(['data' => [
            'id' => $rack->id, 'name' => $rack->name, 'location_id' => $rack->location_id, 'height_u' => $rack->height_u, 'used_u' => $rack->usedUnits(), 'max_power_watts' => $rack->max_power_watts,
            'equipment' => $mounts->map(fn (Mount $mount): array => [
                'id' => $mount->id, 'asset' => isset($found[$mount->asset_id]) ? ['id' => $mount->asset_id, 'tag' => $found[$mount->asset_id]->tag, 'name' => $found[$mount->asset_id]->name] : ['id' => $mount->asset_id],
                'position_u' => $mount->position_u, 'height_u' => $mount->height_u, 'units' => $mount->unitsLabel(), 'face' => $mount->face->value, 'full_depth' => $mount->full_depth, 'power_watts' => $mount->power_watts,
            ])->all(),
        ]]);
    }

    /**
     * List power sources with their load (normal: shared across supplies; worst: if the other side fails).
     */
    public function power(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Rack::class);
        $grid = PowerGrid::read();

        return ApiQuery::collection(ApiQuery::paginate($request, PowerSource::query(), [
            'kind' => fn (Builder $query, string $value) => $query->where('kind', $value),
            'location_id' => fn (Builder $query, string $value) => $query->where('location_id', Str::isUuid($value) ? $value : null),
        ], ['name' => 'name'], 'name'), function (PowerSource $source) use ($grid): array {
            $load = $grid->loadOf($source);

            return [
                'id' => $source->id, 'kind' => $source->kind->value, 'name' => $source->name, 'side' => $source->side?->value, 'upstream_id' => $source->upstream_id, 'rack_id' => $source->rack_id,
                'location_id' => $source->location_id, 'capacity_watts' => $source->capacity_watts, 'load_normal_watts' => $load['normal'], 'load_worst_watts' => $load['worst'], 'devices' => $load['devices'],
            ];
        });
    }

    /**
     * List subnets. Filters: `filter[location_id]`, `filter[vlan_id]`. Sort: `network`.
     */
    public function subnets(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Rack::class);

        return ApiQuery::collection(ApiQuery::paginate($request, Subnet::query()->with('vlan'), [
            'location_id' => fn (Builder $query, string $value) => $query->where('location_id', Str::isUuid($value) ? $value : null),
            'vlan_id' => fn (Builder $query, string $value) => $query->where('vlan_id', Str::isUuid($value) ? $value : null),
        ], ['network' => 'network'], 'network'), fn (Subnet $subnet): array => [
            'id' => $subnet->id, 'network' => $subnet->network, 'gateway' => $subnet->gateway, 'vlan' => $subnet->vlan !== null ? ['id' => $subnet->vlan->id, 'vid' => $subnet->vlan->vid, 'name' => $subnet->vlan->name] : null,
            'location_id' => $subnet->location_id, 'purpose' => $subnet->purpose, 'next_free' => $subnet->nextFree(),
        ]);
    }

    /**
     * List IP addresses. Filters: `filter[subnet_id]`, `filter[status]`, `filter[search]` (address or DNS name). Sort: `address`.
     */
    public function addresses(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Rack::class);

        return ApiQuery::collection(ApiQuery::paginate($request, IpAddress::query(), [
            'subnet_id' => function (Builder $query, string $value): void {
                $subnet = Str::isUuid($value) ? Subnet::query()->find($value) : null;
                $subnet !== null ? $query->whereRaw('address << ?::cidr', [$subnet->network]) : $query->whereRaw('false');
            },
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query->whereRaw('host(address) like ?', [self::like($value)])->orWhere('dns_name', 'ilike', self::like($value))),
        ], ['address' => 'address'], 'address'), fn (IpAddress $address): array => [
            'id' => $address->id, 'address' => $address->host(), 'status' => $address->status->value, 'dns_name' => $address->dns_name,
            'mount_id' => $address->mount_id, 'configuration_item_id' => $address->configuration_item_id, 'description' => $address->description,
        ]);
    }

    private static function like(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }
}
