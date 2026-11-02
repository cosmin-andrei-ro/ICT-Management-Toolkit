<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Exports;

use Generator;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\PhysicalAsset;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;
use Modules\Dcim\Domain\Models\PowerConnection;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;

/**
 * The data centre in a workbook (ADR 0051): racks, what is mounted where,
 * cables, the power chain with its load, and IP addresses. Power figures in
 * watts, so they sort and add up.
 */
final readonly class DcimExport implements ExportSource
{
    public const string TYPE = 'dcim.inventory';

    public function __construct(
        private PhysicalAssets $assets,
        private ConfigurationItems $items,
    ) {}

    public function sheets(Export $export): array
    {
        $paths = Location::paths(false);
        $mounts = Mount::query()->with('rack')->get();
        $assets = $this->assets->find(array_values(array_unique($mounts->map(fn (Mount $mount): string => $mount->asset_id)->all())));
        $grid = PowerGrid::read();

        return [
            new ExportSheet(__('dcim::reports.export.racks'), [
                __('dcim::racks.fields.room'), __('dcim::racks.fields.name'), __('dcim::racks.fields.row'), __('dcim::racks.fields.position'),
                __('dcim::racks.fields.height_u'), __('dcim::reports.export.units_used'), __('dcim::reports.export.power_planned'), __('dcim::racks.fields.max_power_watts'),
            ], $this->rackRows($paths)),
            new ExportSheet(__('dcim::reports.export.equipment'), [
                __('dcim::racks.fields.room'), __('dcim::racks.fields.name'), __('dcim::reports.export.units'), __('dcim::racks.fields.face'), __('dcim::racks.full_depth'),
                __('dcim::reports.export.asset_tag'), __('dcim::reports.export.asset_name'), __('dcim::reports.export.model'), __('dcim::reports.export.serial'),
                __('dcim::racks.fields.power_watts'), __('dcim::reports.export.supplies'), __('dcim::power.redundant'),
            ], $this->equipmentRows(array_values($mounts->all()), $assets, $paths, $grid)),
            new ExportSheet(__('dcim::cabling.cables'), [
                __('dcim::cabling.fields.label'), __('dcim::cabling.fields.type'), __('dcim::cabling.fields.colour'), __('dcim::reports.export.length'), __('dcim::cabling.fields.status'),
                __('dcim::reports.export.a_equipment'), __('dcim::reports.export.a_port'), __('dcim::reports.export.b_equipment'), __('dcim::reports.export.b_port'),
            ], $this->cableRows($assets)),
            new ExportSheet(__('dcim::power.title'), [
                __('dcim::power.fields.name'), __('dcim::power.fields.kind'), __('dcim::power.fields.side'), __('dcim::racks.fields.room'), __('dcim::power.fields.upstream'),
                __('dcim::power.fields.capacity'), __('dcim::reports.export.load_normal'), __('dcim::reports.export.load_worst'), __('dcim::reports.export.unknown_draw'),
            ], $this->powerRows($paths, $grid)),
            new ExportSheet(__('dcim::ipam.title'), [
                __('dcim::ipam.fields.address'), __('dcim::reports.export.subnet'), __('dcim::ipam.fields.status'), __('dcim::ipam.fields.dns_name'), __('dcim::ipam.fields.holder'), __('dcim::ipam.fields.description'),
            ], $this->addressRows($assets)),
        ];
    }

    public function filename(Export $export): string
    {
        return 'data-centre-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @param  array<string, string>  $paths
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function rackRows(array $paths): Generator
    {
        foreach (Rack::query()->with('mounts')->orderBy('name')->get() as $rack) {
            yield [
                $paths[$rack->location_id] ?? null, $rack->name, $rack->row, $rack->position, $rack->height_u,
                $rack->usedUnits(), $rack->mounts->sum(fn (Mount $mount): int => $mount->power_watts ?? 0), $rack->max_power_watts,
            ];
        }
    }

    /**
     * @param  list<Mount>  $mounts
     * @param  array<string, PhysicalAsset>  $assets
     * @param  array<string, string>  $paths
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function equipmentRows(array $mounts, array $assets, array $paths, PowerGrid $grid): Generator
    {
        $supplies = [];

        foreach (PowerConnection::query()->with('source')->get() as $connection) {
            $supplies[$connection->mount_id][] = $connection->source->name;
        }

        foreach ($mounts as $mount) {
            $asset = $assets[$mount->asset_id] ?? null;

            yield [
                $paths[$mount->rack->location_id] ?? null, $mount->rack->name, $mount->unitsLabel(), $mount->face->label(), $mount->full_depth,
                $asset?->tag, $asset?->name, $asset !== null ? trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')) : null, $asset?->serialNumber,
                $mount->power_watts, implode(', ', $supplies[$mount->id] ?? []), $grid->isPowered($mount->id) ? $grid->isRedundant($mount->id) : null,
            ];
        }
    }

    /**
     * @param  array<string, PhysicalAsset>  $assets
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function cableRows(array $assets): Generator
    {
        $name = fn (Port $port): ?string => ($assets[$port->mount->asset_id] ?? null)?->label();

        foreach (Cable::query()->with('ends.port.mount')->lazyById(200) as $cable) {
            [$a, $b] = [$cable->ends->firstWhere('end', 'a')?->port, $cable->ends->firstWhere('end', 'b')?->port];

            yield [
                $cable->label, $cable->type->label(), $cable->colour, $cable->length_m !== null ? (float) $cable->length_m : null, $cable->status->label(),
                $a !== null ? $name($a) : null, $a?->name, $b !== null ? $name($b) : null, $b?->name,
            ];
        }
    }

    /**
     * @param  array<string, string>  $paths
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function powerRows(array $paths, PowerGrid $grid): Generator
    {
        foreach (PowerSource::query()->with('upstream')->orderBy('name')->get() as $source) {
            $load = $grid->loadOf($source);

            yield [
                $source->name, $source->kind->label(), $source->side?->label(), $paths[$source->location_id] ?? null, $source->upstream?->name,
                $source->capacity_watts, (int) round($load['normal']), (int) round($load['worst']), $load['unknown'],
            ];
        }
    }

    /**
     * @param  array<string, PhysicalAsset>  $assets
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function addressRows(array $assets): Generator
    {
        $records = IpAddress::query()->with('mount')->orderBy('address')->get();
        $items = $this->items->find(array_values(array_unique(array_filter($records->map(fn (IpAddress $address): ?string => $address->configuration_item_id)->all()))));

        foreach ($records as $address) {
            $holder = match (true) {
                $address->mount !== null => ($assets[$address->mount->asset_id] ?? null)?->label(),
                $address->configuration_item_id !== null => ($items[$address->configuration_item_id] ?? null)?->name,
                default => null,
            };

            yield [$address->host(), Subnet::holding($address->host())?->network, $address->status->label(), $address->dns_name, $holder, $address->description];
        }
    }
}
