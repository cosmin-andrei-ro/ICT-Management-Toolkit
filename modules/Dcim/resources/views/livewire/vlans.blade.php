<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('dcim.ipam.index') }}" class="text-sm text-gray-500 underline">← {{ __('dcim::ipam.title') }}</a>
        <h1 class="text-xl font-semibold">{{ __('dcim::ipam.vlans') }}</h1>
    </header>
    @error('vlans') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    @php($canManage = auth()->user()?->can('create', \Modules\Dcim\Domain\Models\Rack::class))
    <div class="overflow-x-auto rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.vid') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.name') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.location') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.subnets_count') }}</th>
                    <th scope="col" class="py-2 font-medium"><span class="sr-only">{{ __('dcim::cabling.actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($vlans as $vlan)
                    <tr wire:key="vlan-{{ $vlan->id }}">
                        <td class="py-2 pr-4 font-mono">{{ $vlan->vid }}</td>
                        <td class="py-2 pr-4">{{ $vlan->name }} @if ($vlan->description) <span class="block text-xs text-gray-500">{{ $vlan->description }}</span> @endif</td>
                        <td class="py-2 pr-4">{{ $vlan->location_id ? ($allLocations[$vlan->location_id] ?? '—') : __('dcim::ipam.everywhere') }}</td>
                        <td class="py-2 pr-4">{{ $vlan->subnets_count }}</td>
                        <td class="py-2 text-right text-xs">
                            @if ($canManage)
                                <button type="button" wire:click="edit('{{ $vlan->id }}')" class="font-medium text-blue-700 underline">{{ __('dcim::ipam.edit') }}<span class="sr-only">: {{ $vlan->label() }}</span></button>
                                <button type="button" wire:click="delete('{{ $vlan->id }}')" wire:confirm="{{ __('dcim::ipam.delete_vlan_confirm') }}" class="ml-3 font-medium text-red-700 underline">{{ __('dcim::ipam.delete') }}<span class="sr-only">: {{ $vlan->label() }}</span></button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-600">{{ __('dcim::ipam.no_vlans') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canManage)
        <form wire:submit="save" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200" novalidate>
            <div>
                <label for="vid" class="block font-medium">{{ __('dcim::ipam.fields.vid') }}</label>
                <input id="vid" type="number" min="1" max="4094" wire:model="vid" class="mt-1 block w-24 rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="name" class="block font-medium">{{ __('dcim::ipam.fields.name') }}</label>
                <input id="name" type="text" maxlength="64" wire:model="name" class="mt-1 block rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="vlanLocationId" class="block font-medium">{{ __('dcim::ipam.fields.location') }}</label>
                <select id="vlanLocationId" wire:model="vlanLocationId" class="mt-1 block rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('dcim::ipam.everywhere') }}</option>
                    @foreach ($locations as $id => $path)
                        <option wire:key="loc-{{ $id }}" value="{{ $id }}">{{ $path }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="description" class="block font-medium">{{ __('dcim::ipam.fields.description') }}</label>
                <input id="description" type="text" maxlength="255" wire:model="description" class="mt-1 block rounded-md border-gray-300 text-sm">
            </div>
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ $editingId ? __('dcim::ipam.save') : __('dcim::ipam.add_vlan') }}</button>
            @if ($editingId) <button type="button" wire:click="cancel" class="rounded-md bg-white px-4 py-2 ring-1 ring-gray-300">{{ __('dcim::racks.cancel') }}</button> @endif
            @foreach (['vid', 'name', 'vlanLocationId'] as $field) @error($field) <p class="basis-full text-red-600">{{ $message }}</p> @enderror @endforeach
        </form>
    @endif
</div>
