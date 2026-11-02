<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $rackId ? route('dcim.racks.show', $rackId) : route('dcim.racks.index') }}" class="text-sm text-gray-500 underline">{{ __('dcim::racks.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($rackId ? 'dcim::racks.edit' : 'dcim::racks.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="locationId" class="block text-sm font-medium">{{ __('dcim::racks.fields.room') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <select id="locationId" wire:model="locationId" required class="mt-1 block w-full rounded-md border-gray-300">
                <option value="">{{ __('dcim::racks.choose_room') }}</option>
                @foreach ($locations as $id => $path)
                    <option wire:key="location-{{ $id }}" value="{{ $id }}">{{ $path }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">{{ __('dcim::racks.room_help') }}</p>
            @error('locationId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'name' => ['name', 'text', true],
                'row' => ['row', 'text', false],
                'position' => ['position', 'number', false],
                'heightU' => ['height_u', 'number', true],
                'maxPowerWatts' => ['max_power_watts', 'number', false],
            ] as $property => [$label, $type, $required])
                <div wire:key="field-{{ $property }}">
                    <label for="{{ $property }}" class="block text-sm font-medium">{{ __('dcim::racks.fields.'.$label) }} @if ($required)<span class="text-red-600" aria-hidden="true">*</span>@endif</label>
                    <input id="{{ $property }}" type="{{ $type }}" wire:model="{{ $property }}" @if ($type === 'number') min="1" @endif @required($required) class="mt-1 block w-full rounded-md border-gray-300">
                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium">{{ __('dcim::racks.fields.notes') }}</label>
            <textarea id="notes" wire:model="notes" rows="3" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('dcim::racks.save') }}</button>
    </form>
</div>
