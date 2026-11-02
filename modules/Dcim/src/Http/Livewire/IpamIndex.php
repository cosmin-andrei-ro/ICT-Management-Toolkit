<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Dcim\Domain\IpNetwork;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;

/**
 * Every subnet in network order, nested by containment, with how full it
 * is; search by address, network or purpose.
 */
#[Layout('core::components.layouts.app')]
final class IpamIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Rack::class);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $term = trim($this->q);
        $address = IpNetwork::address($term);
        $cidr = IpNetwork::parse($term)?->cidr();

        $subnets = Subnet::query()->with('vlan')
            ->select('dcim_subnets.*')
            ->selectRaw('(SELECT count(*) FROM dcim_subnets p WHERE p.organization_id = dcim_subnets.organization_id AND p.network >> dcim_subnets.network) AS depth')
            ->selectRaw('(SELECT count(*) FROM dcim_ip_addresses a WHERE a.organization_id = dcim_subnets.organization_id AND a.address << dcim_subnets.network) AS used')
            ->when($address !== null, fn ($query) => $query->whereRaw('network >> ?::inet', [$address]))
            ->when($address === null && $cidr !== null, fn ($query) => $query->where(fn ($match) => $match->whereRaw('network <<= ?::cidr', [$cidr])->orWhereRaw('network >> ?::cidr', [$cidr])))
            ->when($address === null && $cidr === null && $term !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('purpose', LinkedRecords::like($term))
                ->orWhereLike('notes', LinkedRecords::like($term))
                ->orWhereHas('vlan', fn ($vlan) => $vlan->whereLike('name', LinkedRecords::like($term)))))
            ->orderBy('network')
            ->paginate(100);

        return view('dcim::livewire.ipam-index', [
            'subnets' => $subnets,
            'found' => $address !== null ? IpAddress::query()->whereRaw('address = ?::inet', [$address])->first() : null,
            'searchedAddress' => $address,
        ])->title(__('dcim::ipam.title'));
    }
}
