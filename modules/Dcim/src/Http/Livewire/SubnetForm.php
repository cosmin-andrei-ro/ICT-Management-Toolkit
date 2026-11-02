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
use Modules\Dcim\Domain\Models\Subnet;
use Modules\Dcim\Domain\Models\Vlan;

#[Layout('core::components.layouts.app')]
final class SubnetForm extends Component
{
    #[Locked]
    public ?string $subnetId = null;

    public string $network = '';

    public string $gateway = '';

    public string $vlanId = '';

    public string $locationId = '';

    public string $purpose = '';

    public string $notes = '';

    public function mount(?Subnet $subnet = null): void
    {
        $this->authorize('create', Rack::class);

        if ($subnet !== null && $subnet->exists) {
            $this->subnetId = $subnet->id;
            $this->network = $subnet->network;
            $this->gateway = $subnet->gateway !== null ? explode('/', $subnet->gateway)[0] : '';
            $this->vlanId = $subnet->vlan_id ?? '';
            $this->locationId = $subnet->location_id ?? '';
            $this->purpose = $subnet->purpose ?? '';
            $this->notes = $subnet->notes ?? '';
        }
    }

    public function save(ManageIpam $ipam): void
    {
        $this->authorize('create', Rack::class);
        $this->resetErrorBag();
        $this->validate([
            'network' => ['required', 'string', 'max:64'],
            'gateway' => ['nullable', 'string', 'max:64'],
            'vlanId' => ['nullable', 'uuid'],
            'locationId' => ['nullable', 'uuid'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $subnet = $ipam->saveSubnet($this->subnetId !== null ? Subnet::query()->findOrFail($this->subnetId) : null, [
            'network' => $this->network,
            'gateway' => trim($this->gateway) !== '' ? trim($this->gateway) : null,
            'vlan_id' => $this->vlanId !== '' ? $this->vlanId : null,
            'location_id' => $this->locationId !== '' ? $this->locationId : null,
            'purpose' => trim($this->purpose) !== '' ? trim($this->purpose) : null,
            'notes' => trim($this->notes) !== '' ? trim($this->notes) : null,
        ]);

        $this->redirectRoute('dcim.ipam.subnets.show', $subnet);
    }

    public function render(): View
    {
        return view('dcim::livewire.subnet-form', [
            'vlans' => Vlan::query()->orderBy('vid')->get(),
            'locations' => Location::paths(),
        ])->title(__($this->subnetId !== null ? 'dcim::ipam.edit_subnet' : 'dcim::ipam.new_subnet'));
    }
}
