<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('dcim.power.index') }}" class="text-sm text-gray-500 underline">{{ __('dcim::power.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $source->name }}</h1>
            <p class="text-sm text-gray-600">
                {{ $source->kind->label() }}@if ($source->side) · {{ $source->side->label() }}@endif · {{ $room }}
                @if ($source->rack) · <a href="{{ route('dcim.racks.show', $source->rack) }}" class="text-blue-700 underline">{{ __('dcim::racks.rack', ['name' => $source->rack->name]) }}</a> @endif
                @if ($source->asset_id && ($labels[$source->asset_id] ?? null)) · <a href="{{ $labels[$source->asset_id]->url }}" class="text-blue-700 underline">{{ $labels[$source->asset_id]->label() }}</a> @endif
            </p>
            @if ($chain !== [])
                <p class="text-sm text-gray-600">{{ __('dcim::power.fed_by') }}: {{ collect($chain)->map(fn ($id) => $names[$id] ?? '?')->implode(' ← ') }}</p>
            @endif
        </div>
        @if ($canManage)
            <div class="flex gap-2">
                <a href="{{ route('dcim.power.edit', $source) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('dcim::power.edit') }}</a>
                @if ($source->downstream->isEmpty() && $source->connections->isEmpty())
                    <button type="button" wire:click="delete" wire:confirm="{{ __('dcim::power.delete_confirm') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('dcim::power.delete') }}</button>
                @endif
            </div>
        @endif
    </header>
    @error('source') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    <section aria-labelledby="load-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="load-heading" class="text-sm font-semibold">{{ __('dcim::power.load') }}</h2>
        <x-dcim::power-load :load="$load" :capacity="$source->capacity_watts" :warn="$warn" />
        <p class="text-xs text-gray-500">{{ __('dcim::power.load_help') }}</p>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="fed-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="fed-heading" class="text-sm font-semibold">{{ __('dcim::power.feeds') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($source->downstream as $child)
                    <li wire:key="child-{{ $child->id }}" class="py-1"><a href="{{ route('dcim.power.show', $child) }}" class="text-blue-700 underline">{{ $child->name }}</a> <span class="text-xs text-gray-500">{{ $child->kind->label() }}</span></li>
                @endforeach
                @foreach ($source->connections as $connection)
                    <li wire:key="connection-{{ $connection->id }}" class="py-1">
                        <a href="{{ route('dcim.equipment.show', $connection->mount_id) }}" class="text-blue-700 underline">{{ ($labels[$connection->mount->asset_id] ?? null)?->label() ?? __('dcim::racks.unknown_asset') }}</a>
                        <span class="text-xs text-gray-500">{{ __('dcim::racks.rack', ['name' => $connection->mount->rack->name]) }}@if ($connection->outlet) · {{ __('dcim::power.outlet_value', ['outlet' => $connection->outlet]) }}@endif @if ($connection->mount->power_watts !== null) · {{ $connection->mount->power_watts }} W @endif</span>
                    </li>
                @endforeach
                @if ($source->downstream->isEmpty() && $source->connections->isEmpty())
                    <li class="py-1 text-gray-600">{{ __('dcim::power.nothing_fed') }}</li>
                @endif
            </ul>
        </section>

        <section aria-labelledby="failure-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="failure-heading" class="text-sm font-semibold">{{ __('dcim::power.if_fails', ['name' => $source->name]) }}</h2>
            <div>
                <h3 class="text-xs font-semibold text-red-800">{{ trans_choice('dcim::power.lost', count($failure['lost']), ['count' => count($failure['lost'])]) }}</h3>
                <ul class="text-sm">
                    @foreach ($failure['lost'] as $mount)
                        <li wire:key="lost-{{ $mount->id }}"><a href="{{ route('dcim.equipment.show', $mount) }}" class="text-blue-700 underline">{{ ($labels[$mount->asset_id] ?? null)?->label() ?? __('dcim::racks.unknown_asset') }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h3 class="text-xs font-semibold text-amber-800">{{ trans_choice('dcim::power.degraded', count($failure['degraded']), ['count' => count($failure['degraded'])]) }}</h3>
                <ul class="text-sm">
                    @foreach ($failure['degraded'] as $mount)
                        <li wire:key="degraded-{{ $mount->id }}"><a href="{{ route('dcim.equipment.show', $mount) }}" class="text-blue-700 underline">{{ ($labels[$mount->asset_id] ?? null)?->label() ?? __('dcim::racks.unknown_asset') }}</a></li>
                    @endforeach
                </ul>
            </div>
            @if ($impact !== [])
                <div>
                    <h3 class="text-xs font-semibold">{{ __('dcim::power.services') }}</h3>
                    <ul class="text-sm">
                        @foreach ($impact as $item)
                            <li wire:key="item-{{ $item->id }}"><a href="{{ $item->url }}" class="text-blue-700 underline">{{ $item->name }}</a> <span class="text-xs text-gray-500">{{ $item->typeName }} · {{ $item->criticalityLabel }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>

    @if ($source->notes) <p class="whitespace-pre-line text-sm text-gray-700">{{ $source->notes }}</p> @endif

    <x-core::record-history :record="$source" :can-change="auth()->user()->can('create', \Modules\Dcim\Domain\Models\Rack::class)" />
</div>
