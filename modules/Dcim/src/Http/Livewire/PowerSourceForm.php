<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Application\Actions\SavePowerSource;
use Modules\Dcim\Domain\Enums\PowerKind;
use Modules\Dcim\Domain\Enums\PowerSide;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;

#[Layout('core::components.layouts.app')]
final class PowerSourceForm extends Component
{
    #[Locked]
    public ?string $sourceId = null;

    public string $locationId = '';

    public string $kind = 'pdu';

    public string $name = '';

    public string $side = '';

    public string $upstreamId = '';

    public string $assetSearch = '';

    public string $assetId = '';

    public string $rackId = '';

    public string $capacityWatts = '';

    public string $notes = '';

    public function mount(?PowerSource $source = null): void
    {
        if ($source === null || ! $source->exists) {
            $this->authorize('create', Rack::class);

            return;
        }

        $this->authorize('create', Rack::class);
        $this->sourceId = $source->id;
        $this->locationId = $source->location_id;
        $this->kind = $source->kind->value;
        $this->name = $source->name;
        $this->side = $source->side->value ?? '';
        $this->upstreamId = $source->upstream_id ?? '';
        $this->assetId = $source->asset_id ?? '';
        $this->rackId = $source->rack_id ?? '';
        $this->capacityWatts = (string) ($source->capacity_watts ?? '');
        $this->notes = $source->notes ?? '';
    }

    public function chooseAsset(string $assetId): void
    {
        $this->assetId = $assetId;
        $this->assetSearch = '';
    }

    public function clearAsset(): void
    {
        $this->assetId = '';
    }

    public function save(SavePowerSource $save): void
    {
        $this->authorize('create', Rack::class);
        $this->resetErrorBag();
        $this->validate([
            'locationId' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(PowerKind::class)],
            'name' => ['required', 'string', 'max:64'],
            'side' => ['nullable', Rule::enum(PowerSide::class)],
            'upstreamId' => ['nullable', 'uuid'],
            'assetId' => ['nullable', 'uuid'],
            'rackId' => ['nullable', 'uuid'],
            'capacityWatts' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $source = $save->handle($this->sourceId !== null ? PowerSource::query()->findOrFail($this->sourceId) : null, [
            'location_id' => $this->locationId,
            'kind' => PowerKind::from($this->kind),
            'name' => trim($this->name),
            'side' => PowerSide::tryFrom($this->side),
            'upstream_id' => $this->upstreamId !== '' ? $this->upstreamId : null,
            'asset_id' => $this->assetId !== '' ? $this->assetId : null,
            'rack_id' => $this->rackId !== '' ? $this->rackId : null,
            'capacity_watts' => $this->capacityWatts !== '' ? (int) $this->capacityWatts : null,
            'notes' => trim($this->notes) !== '' ? trim($this->notes) : null,
        ]);

        $this->redirectRoute('dcim.power.show', $source);
    }

    public function render(PhysicalAssets $assets): View
    {
        $search = trim($this->assetSearch);

        return view('dcim::livewire.power-form', [
            'locations' => Location::paths(),
            'kinds' => PowerKind::cases(),
            'sides' => PowerSide::cases(),
            'upstreams' => PowerSource::query()->where('kind', '!=', PowerKind::Pdu->value)->when($this->sourceId !== null, fn ($query) => $query->whereKeyNot($this->sourceId))->orderBy('name')->get(),
            'racks' => Rack::query()->orderBy('name')->get(),
            'asset' => $this->assetId !== '' ? ($assets->find([$this->assetId])[$this->assetId] ?? null) : null,
            'candidates' => mb_strlen($search) >= 2 ? $assets->search($search, 10) : [],
        ])->title(__($this->sourceId !== null ? 'dcim::power.edit' : 'dcim::power.new'));
    }
}
