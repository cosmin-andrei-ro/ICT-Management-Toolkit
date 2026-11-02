<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Application\Actions\ManageIpam;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Vlan;

/**
 * VLANs: their numbers, names and where they apply.
 */
#[Layout('core::components.layouts.app')]
final class VlanIndex extends Component
{
    #[Locked]
    public ?string $editingId = null;

    public string $vid = '';

    public string $name = '';

    public string $vlanLocationId = '';

    public string $description = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Rack::class);
    }

    public function edit(string $vlanId): void
    {
        $this->authorize('create', Rack::class);
        $vlan = Vlan::query()->findOrFail($vlanId);
        $this->resetErrorBag();
        $this->editingId = $vlan->id;
        $this->vid = (string) $vlan->vid;
        $this->name = $vlan->name;
        $this->vlanLocationId = $vlan->location_id ?? '';
        $this->description = $vlan->description ?? '';
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'vid', 'name', 'vlanLocationId', 'description');
    }

    public function save(ManageIpam $ipam): void
    {
        $this->authorize('create', Rack::class);
        $this->resetErrorBag();
        $this->validate([
            'vid' => ['required', 'integer', 'min:1', 'max:4094'],
            'name' => ['required', 'string', 'max:64'],
            'vlanLocationId' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $ipam->saveVlan($this->editingId !== null ? Vlan::query()->findOrFail($this->editingId) : null, [
            'vid' => (int) $this->vid,
            'name' => trim($this->name),
            'location_id' => $this->vlanLocationId !== '' ? $this->vlanLocationId : null,
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
        ]);
        $this->cancel();
    }

    public function delete(string $vlanId, ManageIpam $ipam): void
    {
        $this->authorize('create', Rack::class);
        $this->resetErrorBag();
        $ipam->deleteVlan(Vlan::query()->findOrFail($vlanId));
    }

    public function render(): View
    {
        return view('dcim::livewire.vlans', [
            'vlans' => Vlan::query()->withCount('subnets')->orderBy('vid')->get(),
            'locations' => Location::paths(),
            'allLocations' => Location::paths(false),
        ])->title(__('dcim::ipam.vlans'));
    }
}
