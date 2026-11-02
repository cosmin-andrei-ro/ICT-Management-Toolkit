<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Application\Actions\SavePowerSource;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;

/**
 * A feed, UPS or PDU: what it carries, and what a failure of it takes down,
 * up to the services in the CMDB.
 */
#[Layout('core::components.layouts.app')]
final class ShowPowerSource extends Component
{
    #[Locked]
    public string $sourceId = '';

    public function mount(PowerSource $source): void
    {
        $this->authorize('viewAny', Rack::class);
        $this->sourceId = $source->id;
    }

    public function delete(SavePowerSource $save): void
    {
        $this->authorize('create', Rack::class);
        $save->delete(PowerSource::query()->findOrFail($this->sourceId));
        $this->redirectRoute('dcim.power.index');
    }

    public function render(PhysicalAssets $assets, ConfigurationItems $items): View
    {
        $source = PowerSource::query()->with(['upstream', 'downstream', 'rack', 'connections.mount.rack'])->findOrFail($this->sourceId);
        $grid = PowerGrid::read();
        $failure = $grid->failureOf($source);
        $mounts = collect([...$failure['lost'], ...$failure['degraded'], ...$source->connections->map(fn ($connection): Mount => $connection->mount)->all()]);
        $assetIds = array_values(array_unique(array_filter([...$mounts->map(fn (Mount $mount): string => $mount->asset_id)->all(), $source->asset_id])));
        $lostAssetIds = array_values(array_unique(array_map(fn (Mount $mount): string => $mount->asset_id, $failure['lost'])));
        $itemIds = [];

        foreach ($items->linkedIds('itam.asset', $lostAssetIds) as $linked) {
            array_push($itemIds, ...$linked);
        }

        $user = auth()->user();

        return view('dcim::livewire.power-source', [
            'source' => $source,
            'room' => Location::paths(false)[$source->location_id] ?? $source->location->name,
            'load' => $grid->loadOf($source),
            'warn' => config()->integer('dcim.power.warn_percent', 80),
            'failure' => $failure,
            'impact' => $items->impactOfItems(array_values(array_unique($itemIds))),
            'labels' => $assets->find($assetIds),
            'chain' => array_slice($grid->chain($source->id), 1),
            'names' => PowerSource::query()->pluck('name', 'id')->all(),
            'canManage' => $user instanceof User && $user->can('create', Rack::class),
        ])->title($source->name);
    }
}
