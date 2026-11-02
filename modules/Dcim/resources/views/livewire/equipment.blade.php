<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('dcim.racks.show', $equipment->rack) }}" class="text-sm text-gray-500 underline">← {{ __('dcim::racks.rack', ['name' => $equipment->rack->name]) }}</a>
        <h1 class="text-xl font-semibold">{{ $asset?->label() ?? __('dcim::racks.unknown_asset') }}</h1>
        <p class="text-sm text-gray-600">
            {{ $equipment->unitsLabel() }} · {{ $equipment->face->label() }}
            @if ($asset?->model) · {{ trim($asset->manufacturer.' '.$asset->model) }} @endif
            @if ($asset) · <a href="{{ $asset->url }}" class="text-blue-700 underline">{{ __('dcim::cabling.asset_record') }}</a> @endif
        </p>
    </header>

    <section aria-labelledby="power-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="power-heading" class="text-sm font-semibold">{{ __('dcim::power.equipment_heading') }}</h2>
            @if (! $powered)
                <span class="text-xs text-gray-500">{{ __('dcim::power.not_powered') }}</span>
            @elseif ($redundant)
                <span class="rounded bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-green-200">{{ __('dcim::power.redundant') }}</span>
            @else
                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-amber-300">{{ __('dcim::power.single_fed') }}</span>
            @endif
        </div>
        <p class="text-xs text-gray-600">{{ $equipment->power_watts !== null ? __('dcim::power.draws', ['watts' => $equipment->power_watts]) : __('dcim::power.draw_unknown') }}</p>
        <ul class="divide-y divide-gray-100 text-sm">
            @foreach ($powerConnections as $connection)
                <li wire:key="power-{{ $connection->id }}" class="flex items-center justify-between gap-2 py-1">
                    <span><a href="{{ route('dcim.power.show', $connection->source) }}" class="text-blue-700 underline">{{ $connection->source->name }}</a> <span class="text-xs text-gray-500">{{ $connection->source->kind->label() }}@if ($connection->source->side) · {{ $connection->source->side->label() }}@endif @if ($connection->outlet) · {{ __('dcim::power.outlet_value', ['outlet' => $connection->outlet]) }}@endif</span></span>
                    @if ($canManage)
                        <button type="button" wire:click="unplug('{{ $connection->id }}')" wire:confirm="{{ __('dcim::power.unplug_confirm') }}" class="text-xs font-medium text-red-700 underline">{{ __('dcim::power.unplug') }}<span class="sr-only">: {{ $connection->source->name }}</span></button>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($canManage)
            <form wire:submit="plugIn" class="flex flex-wrap items-end gap-3 text-sm" novalidate>
                <div>
                    <label for="powerSourceId" class="block font-medium">{{ __('dcim::power.plug_into') }}</label>
                    <select id="powerSourceId" wire:model="powerSourceId" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('dcim::power.choose_source') }}</option>
                        @foreach ($powerSources as $option)
                            <option wire:key="source-{{ $option->id }}" value="{{ $option->id }}">{{ $option->name }} ({{ $option->kind->label() }}@if ($option->side), {{ $option->side->label() }}@endif)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="outlet" class="block font-medium">{{ __('dcim::power.fields.outlet') }}</label>
                    <input id="outlet" type="text" maxlength="32" wire:model="outlet" class="mt-1 block w-28 rounded-md border-gray-300 text-sm">
                </div>
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('dcim::power.plug') }}</button>
            </form>
            @error('powerSourceId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('outlet') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
    </section>

    @if ($ipAddresses->isNotEmpty())
        <section aria-labelledby="ip-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="ip-heading" class="text-sm font-semibold">{{ __('dcim::ipam.addresses') }}</h2>
            <ul class="text-sm">
                @foreach ($ipAddresses as $row)
                    <li wire:key="ip-{{ $row['address']->id }}">
                        <span class="font-mono">{{ $row['address']->host() }}</span>
                        <span class="text-xs text-gray-500">{{ $row['address']->status->label() }}@if ($row['address']->dns_name) · {{ $row['address']->dns_name }}@endif</span>
                        @if ($row['subnet']) · <a href="{{ route('dcim.ipam.subnets.show', $row['subnet']) }}" class="font-mono text-xs text-blue-700 underline">{{ $row['subnet']->network }}</a> @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="ports-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="ports-heading" class="text-sm font-semibold">{{ __('dcim::cabling.ports') }}</h2>
        @error('ports') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.fields.port') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.fields.kind') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.fields.cable') }}</th>
                        <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.fields.connected_to') }}</th>
                        <th scope="col" class="py-2 font-medium"><span class="sr-only">{{ __('dcim::cabling.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($equipment->ports as $port)
                        @php($cable = $port->cableEnd?->cable)
                        @php($far = $cable?->farEnd($port))
                        @php($through = $port->passesTo())
                        <tr wire:key="port-{{ $port->id }}">
                            <td class="py-2 pr-4">
                                <span class="font-medium">{{ $port->name }}</span>
                                <span class="block text-xs text-gray-500">{{ $port->side->label() }}@if ($through) · {{ __('dcim::cabling.wired_to', ['port' => $through->name]) }}@endif</span>
                            </td>
                            <td class="py-2 pr-4">{{ $port->kind->label() }}</td>
                            <td class="py-2 pr-4">
                                @if ($cable)
                                    {{ $cable->displayLabel() }}
                                    <span class="block text-xs text-gray-500">{{ collect([$cable->type->label(), $cable->colour, $cable->length_m ? $cable->length_m.' m' : null, $cable->status === \Modules\Dcim\Domain\Enums\CableStatus::Planned ? $cable->status->label() : null])->filter()->implode(' · ') }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">@if ($far) <x-dcim::port-ref :port="$far" :labels="$labels" /> @else <span class="text-gray-400">—</span> @endif</td>
                            <td class="py-2 text-right text-xs">
                                <div class="flex flex-wrap justify-end gap-3">
                                    @if ($cable)
                                        <button type="button" wire:click="trace('{{ $port->id }}')" class="font-medium text-blue-700 underline">{{ __('dcim::cabling.trace') }}<span class="sr-only">: {{ $port->name }}</span></button>
                                    @endif
                                    @if ($canManage)
                                        @if ($cable)
                                            <button type="button" wire:click="editCable('{{ $port->id }}')" class="font-medium text-blue-700 underline">{{ __('dcim::cabling.edit_cable') }}<span class="sr-only">: {{ $port->name }}</span></button>
                                            <button type="button" wire:click="disconnect('{{ $port->id }}')" wire:confirm="{{ __('dcim::cabling.disconnect_confirm') }}" class="font-medium text-red-700 underline">{{ __('dcim::cabling.disconnect') }}<span class="sr-only">: {{ $port->name }}</span></button>
                                        @else
                                            <button type="button" wire:click="startConnecting('{{ $port->id }}')" class="font-medium text-blue-700 underline">{{ __('dcim::cabling.connect') }}<span class="sr-only">: {{ $port->name }}</span></button>
                                            @unless ($port->side === \Modules\Dcim\Domain\Enums\MountFace::Rear && $port->frontPort)
                                                <button type="button" wire:click="deletePort('{{ $port->id }}')" wire:confirm="{{ __('dcim::cabling.delete_port_confirm') }}" class="font-medium text-red-700 underline">{{ __('dcim::cabling.delete_port') }}<span class="sr-only">: {{ $port->name }}</span></button>
                                            @endunless
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($tracingPortId === $port->id)
                            <tr wire:key="trace-{{ $port->id }}">
                                <td colspan="5" class="bg-gray-50 p-3">
                                    <p class="mb-2 text-xs font-semibold">{{ __('dcim::cabling.path') }}</p>
                                    <ol class="space-y-1 text-sm">
                                        @foreach ($path as $step)
                                            <li wire:key="step-{{ $loop->index }}" class="flex flex-wrap items-center gap-2">
                                                @if ($step['via'])
                                                    <span class="text-xs text-gray-500">↓ {{ __('dcim::cabling.via_cable', ['cable' => $step['via']->displayLabel(), 'type' => $step['via']->type->label()]) }}</span>
                                                    <span class="basis-full"></span>
                                                @elseif ($step['throughPanel'])
                                                    <span class="text-xs text-gray-500">↓ {{ __('dcim::cabling.through_panel') }}</span>
                                                    <span class="basis-full"></span>
                                                @endif
                                                <span><x-dcim::port-ref :port="$step['port']" :labels="$labels" /> <span class="text-xs text-gray-500">({{ __('dcim::racks.rack', ['name' => $step['port']->mount->rack->name]) }})</span></span>
                                            </li>
                                        @endforeach
                                    </ol>
                                    <p class="mt-2 text-xs text-gray-500">{{ __('dcim::cabling.path_end') }}</p>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="py-2 text-gray-600">{{ __('dcim::cabling.no_ports') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage && $connectingPortId)
        @php($from = $equipment->ports->firstWhere('id', $connectingPortId))
        <section aria-labelledby="cable-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="cable-heading" class="text-sm font-semibold">{{ $editingCableId ? __('dcim::cabling.edit_cable_heading', ['port' => $from?->name]) : __('dcim::cabling.connect_heading', ['port' => $from?->name]) }}</h2>
            <form wire:submit="saveCable" class="grid gap-3 text-sm sm:grid-cols-2" novalidate>
                @unless ($editingCableId)
                    <div class="sm:col-span-2">
                        <label for="equipmentSearch" class="block font-medium">{{ __('dcim::cabling.fields.other_equipment') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        @if ($otherMount)
                            <p class="mt-1 rounded bg-gray-50 px-2 py-1">{{ ($labels[$otherMount->asset_id] ?? null)?->label() }}</p>
                            <label for="otherPortId" class="mt-2 block font-medium">{{ __('dcim::cabling.fields.other_port') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                            <select id="otherPortId" wire:model="otherPortId" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('dcim::cabling.choose_port') }}</option>
                                @foreach ($otherMount->ports->filter(fn ($port) => $port->cableEnd === null && $port->id !== $connectingPortId) as $port)
                                    <option wire:key="other-{{ $port->id }}" value="{{ $port->id }}">{{ $port->name }} ({{ $port->kind->label() }}, {{ $port->side->label() }})</option>
                                @endforeach
                            </select>
                        @endif
                        <input id="equipmentSearch" type="search" wire:model.live.debounce.300ms="equipmentSearch" placeholder="{{ __('dcim::cabling.equipment_search') }}" class="mt-2 block w-full rounded-md border-gray-300 text-sm">
                        @if ($candidates !== [])
                            <ul class="mt-1 divide-y divide-gray-100 rounded ring-1 ring-gray-200">
                                @foreach ($candidates as $candidate)
                                    <li wire:key="candidate-{{ $candidate['mount']->id }}"><button type="button" wire:click="chooseEquipment('{{ $candidate['mount']->id }}')" class="w-full px-2 py-1 text-left hover:bg-gray-50">{{ $candidate['asset']->label() }} <span class="text-xs text-gray-500">{{ __('dcim::racks.rack', ['name' => $candidate['mount']->rack->name]) }} · {{ $candidate['mount']->unitsLabel() }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                        @error('otherPortId') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endunless
                <div>
                    <label for="cableLabel" class="block font-medium">{{ __('dcim::cabling.fields.label') }}</label>
                    <input id="cableLabel" type="text" maxlength="64" wire:model="cableLabel" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @error('cableLabel') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cableType" class="block font-medium">{{ __('dcim::cabling.fields.type') }}</label>
                    <select id="cableType" wire:model="cableType" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($cableTypes as $type)
                            <option wire:key="type-{{ $type->value }}" value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cableColour" class="block font-medium">{{ __('dcim::cabling.fields.colour') }}</label>
                    <input id="cableColour" type="text" maxlength="32" wire:model="cableColour" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="cableLength" class="block font-medium">{{ __('dcim::cabling.fields.length') }}</label>
                    <input id="cableLength" type="number" step="0.01" min="0.01" wire:model="cableLength" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @error('cableLength') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cableStatus" class="block font-medium">{{ __('dcim::cabling.fields.status') }}</label>
                    <select id="cableStatus" wire:model="cableStatus" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach ($cableStatuses as $status)
                            <option wire:key="status-{{ $status->value }}" value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="cableNotes" class="block font-medium">{{ __('dcim::cabling.fields.notes') }}</label>
                    <input id="cableNotes" type="text" maxlength="2000" wire:model="cableNotes" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ $editingCableId ? __('dcim::cabling.save_cable') : __('dcim::cabling.connect') }}</button>
                    <button type="button" wire:click="cancelCable" class="rounded-md bg-white px-4 py-2 ring-1 ring-gray-300">{{ __('dcim::racks.cancel') }}</button>
                </div>
            </form>
        </section>
    @endif

    @if ($canManage)
        <section aria-labelledby="add-ports-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="add-ports-heading" class="text-sm font-semibold">{{ __('dcim::cabling.add_ports') }}</h2>
            <form wire:submit="addPorts" class="flex flex-wrap items-end gap-3 text-sm" novalidate>
                <div>
                    <label for="pattern" class="block font-medium">{{ __('dcim::cabling.fields.names') }}</label>
                    <input id="pattern" type="text" maxlength="80" wire:model="pattern" placeholder="Gi1/0/[1-48]" class="mt-1 block w-56 rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="kind" class="block font-medium">{{ __('dcim::cabling.fields.kind') }}</label>
                    <select id="kind" wire:model="kind" class="mt-1 block rounded-md border-gray-300 text-sm">
                        @foreach ($kinds as $option)
                            <option wire:key="kind-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="side" class="block font-medium">{{ __('dcim::cabling.fields.side') }}</label>
                    <select id="side" wire:model="side" class="mt-1 block rounded-md border-gray-300 text-sm">
                        @foreach ($sides as $option)
                            <option wire:key="side-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 pb-2"><input type="checkbox" wire:model="patchPanel" class="rounded border-gray-300"> {{ __('dcim::cabling.patch_panel') }}</label>
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('dcim::cabling.add') }}</button>
            </form>
            @error('pattern') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="text-xs text-gray-500">{{ __('dcim::cabling.pattern_help') }}</p>
        </section>
    @endif
</div>
