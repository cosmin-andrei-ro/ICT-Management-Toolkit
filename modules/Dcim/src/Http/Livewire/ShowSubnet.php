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
use Livewire\WithPagination;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Application\Actions\ManageIpam;
use Modules\Dcim\Domain\Enums\AddressStatus;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;

/**
 * A subnet: how full it is, the subnets inside it, and its own addresses,
 * with the form to give one out.
 */
#[Layout('core::components.layouts.app')]
final class ShowSubnet extends Component
{
    use WithPagination;

    #[Locked]
    public string $subnetId = '';

    #[Locked]
    public ?string $editingId = null;

    public string $address = '';

    public string $status = 'active';

    public string $dnsName = '';

    public string $holderSearch = '';

    /** "mount:<id>" or "item:<id>". */
    public string $holder = '';

    public string $description = '';

    public function mount(Subnet $subnet): void
    {
        $this->authorize('viewAny', Rack::class);
        $this->subnetId = $subnet->id;
    }

    public function useNextFree(): void
    {
        $this->authorizeManage();
        $this->address = $this->subnet()->nextFree() ?? '';

        if ($this->address === '') {
            $this->addError('address', __('dcim::ipam.errors.full'));
        }
    }

    public function chooseHolder(string $holder): void
    {
        $this->authorizeManage();
        $this->holder = $holder;
        $this->holderSearch = '';
    }

    public function clearHolder(): void
    {
        $this->holder = '';
    }

    public function edit(string $addressId): void
    {
        $this->authorizeManage();
        $record = IpAddress::query()->findOrFail($addressId);
        $this->resetErrorBag();
        $this->editingId = $record->id;
        $this->address = $record->host();
        $this->status = $record->status->value;
        $this->dnsName = $record->dns_name ?? '';
        $this->holder = $record->mount_id !== null ? 'mount:'.$record->mount_id : ($record->configuration_item_id !== null ? 'item:'.$record->configuration_item_id : '');
        $this->description = $record->description ?? '';
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'address', 'status', 'dnsName', 'holderSearch', 'holder', 'description');
    }

    public function save(ManageIpam $ipam): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $this->validate([
            'address' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::enum(AddressStatus::class)],
            'dnsName' => ['nullable', 'string', 'max:253', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$/'],
            'holder' => ['nullable', 'regex:/^(mount|item):[0-9a-f-]{36}$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        [$kind, $id] = $this->holder !== '' ? explode(':', $this->holder, 2) : [null, null];

        $ipam->saveAddress($this->editingId !== null ? IpAddress::query()->findOrFail($this->editingId) : null, [
            'address' => $this->address,
            'status' => AddressStatus::from($this->status),
            'dns_name' => trim($this->dnsName) !== '' ? mb_strtolower(trim($this->dnsName)) : null,
            'mount_id' => $kind === 'mount' ? $id : null,
            'configuration_item_id' => $kind === 'item' ? $id : null,
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
        ]);
        $this->cancel();
    }

    public function delete(string $addressId, ManageIpam $ipam): void
    {
        $this->authorizeManage();
        $ipam->deleteAddress(IpAddress::query()->findOrFail($addressId));
        $this->cancel();
    }

    public function deleteSubnet(ManageIpam $ipam): void
    {
        $this->authorizeManage();
        $ipam->deleteSubnet($this->subnet());
        $this->redirectRoute('dcim.ipam.index');
    }

    public function render(PhysicalAssets $assets, ConfigurationItems $items): View
    {
        $subnet = $this->subnet();
        $network = $subnet->parsed();
        $own = IpAddress::query()->with('mount.rack')
            ->whereRaw('address << ?::cidr', [$subnet->network])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM dcim_subnets c WHERE c.organization_id = dcim_ip_addresses.organization_id AND c.network << ?::cidr AND dcim_ip_addresses.address << c.network)', [$subnet->network])
            ->orderBy('address')
            ->paginate(100);
        $children = Subnet::query()
            ->whereRaw('network << ?::cidr', [$subnet->network])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM dcim_subnets m WHERE m.organization_id = dcim_subnets.organization_id AND m.network << ?::cidr AND dcim_subnets.network << m.network)', [$subnet->network])
            ->orderBy('network')
            ->get();

        $mountIds = [];
        $itemIds = [];

        foreach ($own as $record) {
            if ($record->mount_id !== null) {
                $mountIds[] = $record->mount_id;
            }

            if ($record->configuration_item_id !== null) {
                $itemIds[] = $record->configuration_item_id;
            }
        }

        [$kind, $holderId] = $this->holder !== '' ? explode(':', $this->holder, 2) : [null, null];

        if ($kind === 'mount') {
            $mountIds[] = (string) $holderId;
        } elseif ($kind === 'item') {
            $itemIds[] = (string) $holderId;
        }

        $mounts = Mount::query()->with('rack')->whereKey(array_values(array_unique($mountIds)))->get()->keyBy('id');
        $search = mb_strtolower(trim($this->holderSearch));
        $equipment = [];

        if (mb_strlen($search) >= 2) {
            $all = Mount::query()->with('rack')->get();
            $found = $assets->find(array_values(array_unique($all->map(fn (Mount $mount): string => $mount->asset_id)->all())));
            $equipment = $all->filter(fn (Mount $mount): bool => isset($found[$mount->asset_id]) && str_contains(mb_strtolower($found[$mount->asset_id]->label()), $search))
                ->take(8)->map(fn (Mount $mount): array => ['mount' => $mount, 'asset' => $found[$mount->asset_id]])->values()->all();
        }

        $used = $subnet->addresses()->count();
        $usable = $network->usable();

        return view('dcim::livewire.subnet', [
            'subnet' => $subnet,
            'network' => $network,
            'room' => $subnet->location_id !== null ? (Location::paths(false)[$subnet->location_id] ?? null) : null,
            'addresses' => $own,
            'children' => $children,
            'used' => $used,
            'usable' => $usable,
            'percent' => $usable !== null && $usable > 0 ? (int) round($used / $usable * 100) : null,
            'assets' => $assets->find(array_values(array_unique($mounts->map(fn (Mount $mount): string => $mount->asset_id)->all()))),
            'mounts' => $mounts,
            'items' => $items->find(array_values(array_unique($itemIds))),
            'equipmentCandidates' => $equipment,
            'itemCandidates' => mb_strlen($search) >= 2 ? $items->search($this->holderSearch, 8) : [],
            'statuses' => AddressStatus::cases(),
            'canManage' => $this->actor()->can('create', Rack::class),
        ])->title($subnet->network);
    }

    private function subnet(): Subnet
    {
        return Subnet::query()->with('vlan')->findOrFail($this->subnetId);
    }

    private function authorizeManage(): void
    {
        $this->authorize('create', Rack::class);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
