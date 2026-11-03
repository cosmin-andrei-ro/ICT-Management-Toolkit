<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('dcim.racks.index') }}" class="text-sm text-gray-500 underline">{{ __('dcim::racks.back') }}</a>
            <h1 class="text-xl font-semibold">{{ __('dcim::racks.rack', ['name' => $rack->name]) }}</h1>
            <p class="text-sm text-gray-600">
                {{ $room }}
                @if ($rack->row) · {{ __('dcim::racks.row_value', ['row' => $rack->row]) }} @endif
                @if ($rack->position) · {{ __('dcim::racks.position_value', ['position' => $rack->position]) }} @endif
                · {{ __('dcim::racks.occupancy', ['used' => $used, 'height' => $rack->height_u, 'percent' => (int) round($used / max(1, $rack->height_u) * 100)]) }}
                · {{ $rack->max_power_watts ? __('dcim::racks.power_of', ['used' => $power, 'max' => $rack->max_power_watts]) : __('dcim::racks.power_only', ['used' => $power]) }}
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('dcim.racks.print', $rack) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('dcim::reports.sheet.button') }}</a>
        @if ($canManage)
                <a href="{{ route('dcim.racks.edit', $rack) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('dcim::racks.edit') }}</a>
                @if ($mounts->isEmpty())
                    <button type="button" wire:click="deleteRack" wire:confirm="{{ __('dcim::racks.delete_confirm') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('dcim::racks.delete') }}</button>
                @endif
        @endif
        </div>
    </header>
    @error('rack') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    @if ($rack->notes) <p class="whitespace-pre-line text-sm text-gray-700">{{ $rack->notes }}</p> @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-dcim::elevation :blocks="$front" :face="\Modules\Dcim\Domain\Enums\MountFace::Front" :assets="$assets" :can-manage="$canManage" :height="$rack->height_u" />
        <x-dcim::elevation :blocks="$rear" :face="\Modules\Dcim\Domain\Enums\MountFace::Rear" :assets="$assets" :can-manage="$canManage" :height="$rack->height_u" />

        <div class="space-y-6">
            <section aria-labelledby="mounted-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 id="mounted-heading" class="text-sm font-semibold">{{ __('dcim::racks.mounted') }}</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($mounts as $mount)
                        @php($asset = $assets[$mount->asset_id] ?? null)
                        <li wire:key="mount-{{ $mount->id }}" class="space-y-1 py-2">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ $asset?->url ?? '#' }}" class="font-medium text-blue-700 underline">{{ $asset?->label() ?? __('dcim::racks.unknown_asset') }}</a>
                                <span class="shrink-0 text-xs text-gray-600">{{ $mount->unitsLabel() }}</span>
                            </div>
                            <p class="text-xs text-gray-600">
                                {{ $mount->face->label() }} · {{ $mount->full_depth ? __('dcim::racks.full_depth') : __('dcim::racks.half_depth') }}
                                @if ($mount->power_watts !== null) · {{ $mount->power_watts }} W @endif
                                @if ($asset?->model) · {{ trim($asset->manufacturer.' '.$asset->model) }} @endif
                            </p>
                            @if ($mount->notes) <p class="text-xs text-gray-600">{{ $mount->notes }}</p> @endif
                            @unless ($canManage)
                                <a href="{{ route('dcim.equipment.show', $mount) }}" class="text-xs font-medium text-blue-700 underline">{{ __('dcim::cabling.ports_link') }}<span class="sr-only">: {{ $asset?->label() }}</span></a>
                            @endunless
                            @if ($canManage)
                                <div class="flex gap-3 text-xs">
                                    <a href="{{ route('dcim.equipment.show', $mount) }}" class="font-medium text-blue-700 underline">{{ __('dcim::cabling.ports_link') }}<span class="sr-only">: {{ $asset?->label() }}</span></a>
                                    <button type="button" wire:click="edit('{{ $mount->id }}')" class="font-medium text-blue-700 underline">{{ __('dcim::racks.move') }}<span class="sr-only">: {{ $asset?->label() }}</span></button>
                                    <button type="button" wire:click="unmount('{{ $mount->id }}')" wire:confirm="{{ __('dcim::racks.unmount_confirm') }}" class="font-medium text-red-700 underline">{{ __('dcim::racks.unmount') }}<span class="sr-only">: {{ $asset?->label() }}</span></button>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-gray-600">{{ __('dcim::racks.empty') }}</li>
                    @endforelse
                </ul>
            </section>

            @if ($canManage)
                <section aria-labelledby="mount-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                    <h2 id="mount-heading" class="text-sm font-semibold">{{ $editingId ? __('dcim::racks.move_heading') : __('dcim::racks.mount_heading') }}</h2>
                    <form wire:submit="save" class="space-y-3 text-sm" novalidate>
                        <div>
                            <label for="assetSearch" class="block font-medium">{{ __('dcim::racks.fields.asset') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                            @if ($assetId !== '')
                                <p class="mt-1 flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1">
                                    <span>{{ ($assets[$assetId] ?? null)?->label() ?? __('dcim::racks.unknown_asset') }}</span>
                                    @unless ($editingId)
                                        <button type="button" wire:click="clearAsset" class="text-xs text-blue-700 underline">{{ __('dcim::racks.change') }}</button>
                                    @endunless
                                </p>
                            @else
                                <input id="assetSearch" type="search" wire:model.live.debounce.300ms="assetSearch" placeholder="{{ __('dcim::racks.asset_search') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                @if ($candidates !== [])
                                    <ul class="mt-1 divide-y divide-gray-100 rounded ring-1 ring-gray-200">
                                        @foreach ($candidates as $candidate)
                                            <li wire:key="candidate-{{ $candidate->id }}">
                                                <button type="button" wire:click="chooseAsset('{{ $candidate->id }}')" class="w-full px-2 py-1 text-left hover:bg-gray-50">{{ $candidate->label() }} <span class="text-xs text-gray-500">{{ $candidate->status }}</span></button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif (mb_strlen(trim($assetSearch)) >= 2)
                                    <p class="mt-1 text-xs text-gray-500">{{ __('dcim::racks.no_assets') }}</p>
                                @endif
                            @endif
                            @error('assetId') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="positionU" class="block font-medium">{{ __('dcim::racks.fields.position_u') }}</label>
                                <input id="positionU" type="number" min="1" max="{{ $rack->height_u }}" wire:model="positionU" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                <p class="mt-1 text-xs text-gray-500">{{ __('dcim::racks.position_help') }}</p>
                            </div>
                            <div>
                                <label for="heightU" class="block font-medium">{{ __('dcim::racks.fields.height_units') }}</label>
                                <input id="heightU" type="number" min="1" max="{{ $rack->height_u }}" wire:model="heightU" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            </div>
                        </div>
                        @error('positionU') <p class="text-red-600">{{ $message }}</p> @enderror
                        @error('heightU') <p class="text-red-600">{{ $message }}</p> @enderror
                        <fieldset class="flex flex-wrap gap-4">
                            <legend class="sr-only">{{ __('dcim::racks.fields.face') }}</legend>
                            @foreach ($faces as $option)
                                <label wire:key="face-{{ $option->value }}" class="flex items-center gap-1"><input type="radio" wire:model="face" value="{{ $option->value }}"> {{ $option->label() }}</label>
                            @endforeach
                            <label class="flex items-center gap-1"><input type="checkbox" wire:model="fullDepth" class="rounded border-gray-300"> {{ __('dcim::racks.full_depth') }}</label>
                        </fieldset>
                        <div>
                            <label for="powerWatts" class="block font-medium">{{ __('dcim::racks.fields.power_watts') }}</label>
                            <input id="powerWatts" type="number" min="0" wire:model="powerWatts" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="mountNotes" class="block font-medium">{{ __('dcim::racks.fields.notes') }}</label>
                            <input id="mountNotes" type="text" maxlength="2000" wire:model="notes" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ $editingId ? __('dcim::racks.save_move') : __('dcim::racks.mount') }}</button>
                            @if ($editingId || $assetId !== '' || $positionU !== '')
                                <button type="button" wire:click="cancel" class="rounded-md bg-white px-4 py-2 ring-1 ring-gray-300">{{ __('dcim::racks.cancel') }}</button>
                            @endif
                        </div>
                    </form>
                </section>
            @endif
        </div>
    </div>

    <x-core::record-history :record="$rack" :can-change="auth()->user()->can('update', $rack)" />
</div>
