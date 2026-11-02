<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $sourceId ? route('dcim.power.show', $sourceId) : route('dcim.power.index') }}" class="text-sm text-gray-500 underline">{{ __('dcim::power.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($sourceId ? 'dcim::power.edit' : 'dcim::power.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="kind" class="block text-sm font-medium">{{ __('dcim::power.fields.kind') }}</label>
                <select id="kind" wire:model.live="kind" class="mt-1 block w-full rounded-md border-gray-300">
                    @foreach ($kinds as $option)
                        <option wire:key="kind-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="name" class="block text-sm font-medium">{{ __('dcim::power.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="name" type="text" maxlength="64" wire:model="name" required class="mt-1 block w-full rounded-md border-gray-300">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="locationId" class="block text-sm font-medium">{{ __('dcim::racks.fields.room') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="locationId" wire:model="locationId" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">{{ __('dcim::racks.choose_room') }}</option>
                    @foreach ($locations as $id => $path)
                        <option wire:key="location-{{ $id }}" value="{{ $id }}">{{ $path }}</option>
                    @endforeach
                </select>
                @error('locationId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="side" class="block text-sm font-medium">{{ __('dcim::power.fields.side') }}</label>
                <select id="side" wire:model="side" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">{{ __('dcim::power.no_side') }}</option>
                    @foreach ($sides as $option)
                        <option wire:key="side-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if ($kind !== 'feed')
                <div>
                    <label for="upstreamId" class="block text-sm font-medium">{{ __('dcim::power.fields.upstream') }}</label>
                    <select id="upstreamId" wire:model="upstreamId" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">{{ __('dcim::power.no_upstream') }}</option>
                        @foreach ($upstreams as $upstream)
                            <option wire:key="upstream-{{ $upstream->id }}" value="{{ $upstream->id }}">{{ $upstream->name }} ({{ $upstream->kind->label() }})</option>
                        @endforeach
                    </select>
                    @error('upstreamId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <label for="capacityWatts" class="block text-sm font-medium">{{ __('dcim::power.fields.capacity') }}</label>
                <input id="capacityWatts" type="number" min="1" wire:model="capacityWatts" class="mt-1 block w-full rounded-md border-gray-300">
                <p class="mt-1 text-xs text-gray-500">{{ __('dcim::power.capacity_help') }}</p>
            </div>
            @if ($kind === 'pdu')
                <div>
                    <label for="rackId" class="block text-sm font-medium">{{ __('dcim::power.fields.rack') }}</label>
                    <select id="rackId" wire:model="rackId" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">—</option>
                        @foreach ($racks as $rack)
                            <option wire:key="rack-{{ $rack->id }}" value="{{ $rack->id }}">{{ $rack->name }}</option>
                        @endforeach
                    </select>
                    @error('rackId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        @if ($kind !== 'feed')
            <div>
                <label for="assetSearch" class="block text-sm font-medium">{{ __('dcim::power.fields.asset') }}</label>
                @if ($asset)
                    <p class="mt-1 flex items-center justify-between rounded bg-gray-50 px-2 py-1 text-sm">{{ $asset->label() }} <button type="button" wire:click="clearAsset" class="text-xs text-blue-700 underline">{{ __('dcim::racks.change') }}</button></p>
                @else
                    <input id="assetSearch" type="search" wire:model.live.debounce.300ms="assetSearch" placeholder="{{ __('dcim::racks.asset_search') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @if ($candidates !== [])
                        <ul class="mt-1 divide-y divide-gray-100 rounded text-sm ring-1 ring-gray-200">
                            @foreach ($candidates as $candidate)
                                <li wire:key="candidate-{{ $candidate->id }}"><button type="button" wire:click="chooseAsset('{{ $candidate->id }}')" class="w-full px-2 py-1 text-left hover:bg-gray-50">{{ $candidate->label() }}</button></li>
                            @endforeach
                        </ul>
                    @endif
                @endif
                @error('assetId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label for="notes" class="block text-sm font-medium">{{ __('dcim::racks.fields.notes') }}</label>
            <textarea id="notes" wire:model="notes" rows="2" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
        </div>

        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('dcim::power.save') }}</button>
    </form>
</div>
