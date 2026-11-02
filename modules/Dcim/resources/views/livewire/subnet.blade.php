<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('dcim.ipam.index') }}" class="text-sm text-gray-500 underline">← {{ __('dcim::ipam.title') }}</a>
            <h1 class="font-mono text-xl font-semibold">{{ $subnet->network }}</h1>
            <p class="text-sm text-gray-600">
                {{ $subnet->purpose ?? __('dcim::ipam.no_purpose') }}
                @if ($subnet->vlan) · VLAN {{ $subnet->vlan->label() }} @endif
                @if ($subnet->gateway) · {{ __('dcim::ipam.gateway_value', ['gateway' => explode('/', $subnet->gateway)[0]]) }} @endif
                @if ($room) · {{ $room }} @endif
            </p>
            <p class="text-sm text-gray-600">{{ $percent !== null ? __('dcim::ipam.usage', ['used' => $used, 'usable' => $usable, 'percent' => $percent]) : trans_choice('dcim::ipam.count', $used, ['count' => $used]) }}</p>
        </div>
        @if ($canManage)
            <div class="flex gap-2">
                <a href="{{ route('dcim.ipam.subnets.edit', $subnet) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('dcim::ipam.edit_subnet') }}</a>
                <button type="button" wire:click="deleteSubnet" wire:confirm="{{ __('dcim::ipam.delete_subnet_confirm') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('dcim::ipam.delete') }}</button>
            </div>
        @endif
    </header>
    @error('subnet') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($children->isNotEmpty())
        <section aria-labelledby="children-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="children-heading" class="text-sm font-semibold">{{ __('dcim::ipam.children') }}</h2>
            <ul class="text-sm">
                @foreach ($children as $child)
                    <li wire:key="child-{{ $child->id }}"><a href="{{ route('dcim.ipam.subnets.show', $child) }}" class="font-mono text-blue-700 underline">{{ $child->network }}</a> <span class="text-gray-600">{{ $child->purpose }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="addresses-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="addresses-heading" class="text-sm font-semibold">{{ __('dcim::ipam.addresses') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.address') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.status') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.dns_name') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.holder') }}</th>
                        <th scope="col" class="py-2 font-medium"><span class="sr-only">{{ __('dcim::cabling.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($addresses as $record)
                        <tr wire:key="address-{{ $record->id }}">
                            <td class="py-2 pr-4 font-mono">{{ $record->host() }}</td>
                            <td class="py-2 pr-4">{{ $record->status->label() }}</td>
                            <td class="py-2 pr-4">{{ $record->dns_name ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                @if ($record->mount_id && ($mount = $mounts[$record->mount_id] ?? null))
                                    <a href="{{ route('dcim.equipment.show', $mount) }}" class="text-blue-700 underline">{{ ($assets[$mount->asset_id] ?? null)?->label() ?? __('dcim::racks.unknown_asset') }}</a>
                                @elseif ($record->configuration_item_id && ($item = $items[$record->configuration_item_id] ?? null))
                                    <a href="{{ $item->url }}" class="text-blue-700 underline">{{ $item->name }}</a> <span class="text-xs text-gray-500">{{ $item->typeName }}</span>
                                @else
                                    —
                                @endif
                                @if ($record->description) <span class="block text-xs text-gray-500">{{ $record->description }}</span> @endif
                            </td>
                            <td class="py-2 text-right text-xs">
                                @if ($canManage)
                                    <button type="button" wire:click="edit('{{ $record->id }}')" class="font-medium text-blue-700 underline">{{ __('dcim::ipam.edit') }}<span class="sr-only">: {{ $record->host() }}</span></button>
                                    <button type="button" wire:click="delete('{{ $record->id }}')" wire:confirm="{{ __('dcim::ipam.delete_address_confirm') }}" class="ml-3 font-medium text-red-700 underline">{{ __('dcim::ipam.delete') }}<span class="sr-only">: {{ $record->host() }}</span></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-gray-600">{{ __('dcim::ipam.no_addresses') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $addresses->links() }}</div>
    </section>

    @if ($canManage)
        <section aria-labelledby="address-form-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="address-form-heading" class="text-sm font-semibold">{{ $editingId ? __('dcim::ipam.edit_address') : __('dcim::ipam.add_address') }}</h2>
            <form wire:submit="save" class="grid gap-3 text-sm sm:grid-cols-2" novalidate>
                <div>
                    <label for="address" class="block font-medium">{{ __('dcim::ipam.fields.address') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <div class="mt-1 flex gap-2">
                        <input id="address" type="text" maxlength="64" wire:model="address" class="block w-full rounded-md border-gray-300 font-mono text-sm">
                        <button type="button" wire:click="useNextFree" class="shrink-0 rounded-md bg-white px-3 ring-1 ring-gray-300">{{ __('dcim::ipam.next_free') }}</button>
                    </div>
                    @error('address') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="block font-medium">{{ __('dcim::ipam.fields.status') }}</label>
                    <select id="status" wire:model="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($statuses as $option)
                            <option wire:key="status-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="dnsName" class="block font-medium">{{ __('dcim::ipam.fields.dns_name') }}</label>
                    <input id="dnsName" type="text" maxlength="253" wire:model="dnsName" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @error('dnsName') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="holderSearch" class="block font-medium">{{ __('dcim::ipam.fields.holder') }}</label>
                    @if ($holder !== '')
                        @php([$holderKind, $holderId] = explode(':', $holder, 2))
                        <p class="mt-1 flex items-center justify-between rounded bg-gray-50 px-2 py-1">
                            <span>{{ $holderKind === 'mount' ? (($assets[$mounts[$holderId]->asset_id ?? ''] ?? null)?->label() ?? '—') : (($items[$holderId] ?? null)?->name ?? '—') }}</span>
                            <button type="button" wire:click="clearHolder" class="text-xs text-blue-700 underline">{{ __('dcim::racks.change') }}</button>
                        </p>
                    @else
                        <input id="holderSearch" type="search" wire:model.live.debounce.300ms="holderSearch" placeholder="{{ __('dcim::ipam.holder_search') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @if ($equipmentCandidates !== [] || $itemCandidates !== [])
                            <ul class="mt-1 divide-y divide-gray-100 rounded ring-1 ring-gray-200">
                                @foreach ($equipmentCandidates as $candidate)
                                    <li wire:key="eq-{{ $candidate['mount']->id }}"><button type="button" wire:click="chooseHolder('mount:{{ $candidate['mount']->id }}')" class="w-full px-2 py-1 text-left hover:bg-gray-50">{{ $candidate['asset']->label() }} <span class="text-xs text-gray-500">{{ __('dcim::racks.rack', ['name' => $candidate['mount']->rack->name]) }}</span></button></li>
                                @endforeach
                                @foreach ($itemCandidates as $candidate)
                                    <li wire:key="ci-{{ $candidate->id }}"><button type="button" wire:click="chooseHolder('item:{{ $candidate->id }}')" class="w-full px-2 py-1 text-left hover:bg-gray-50">{{ $candidate->name }} <span class="text-xs text-gray-500">{{ __('dcim::ipam.cmdb_item', ['type' => $candidate->typeName]) }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                    @error('holder') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="addressDescription" class="block font-medium">{{ __('dcim::ipam.fields.description') }}</label>
                    <input id="addressDescription" type="text" maxlength="255" wire:model="description" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('dcim::ipam.save') }}</button>
                    @if ($editingId) <button type="button" wire:click="cancel" class="rounded-md bg-white px-4 py-2 ring-1 ring-gray-300">{{ __('dcim::racks.cancel') }}</button> @endif
                </div>
            </form>
        </section>
    @endif

    @if ($subnet->notes) <p class="whitespace-pre-line text-sm text-gray-700">{{ $subnet->notes }}</p> @endif
</div>
