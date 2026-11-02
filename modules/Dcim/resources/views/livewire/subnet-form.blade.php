<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $subnetId ? route('dcim.ipam.subnets.show', $subnetId) : route('dcim.ipam.index') }}" class="text-sm text-gray-500 underline">← {{ __('dcim::ipam.title') }}</a>
        <h1 class="text-xl font-semibold">{{ __($subnetId ? 'dcim::ipam.edit_subnet' : 'dcim::ipam.new_subnet') }}</h1>
    </header>
    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="network" class="block text-sm font-medium">{{ __('dcim::ipam.fields.network') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="network" type="text" maxlength="64" wire:model="network" placeholder="10.20.0.0/24" class="mt-1 block w-full rounded-md border-gray-300 font-mono">
                @error('network') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="gateway" class="block text-sm font-medium">{{ __('dcim::ipam.fields.gateway') }}</label>
                <input id="gateway" type="text" maxlength="64" wire:model="gateway" placeholder="10.20.0.1" class="mt-1 block w-full rounded-md border-gray-300 font-mono">
                @error('gateway') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="vlanId" class="block text-sm font-medium">{{ __('dcim::ipam.fields.vlan') }}</label>
                <select id="vlanId" wire:model="vlanId" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">—</option>
                    @foreach ($vlans as $vlan)
                        <option wire:key="vlan-{{ $vlan->id }}" value="{{ $vlan->id }}">{{ $vlan->label() }}</option>
                    @endforeach
                </select>
                @error('vlanId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="locationId" class="block text-sm font-medium">{{ __('dcim::ipam.fields.location') }}</label>
                <select id="locationId" wire:model="locationId" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">—</option>
                    @foreach ($locations as $id => $path)
                        <option wire:key="loc-{{ $id }}" value="{{ $id }}">{{ $path }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label for="purpose" class="block text-sm font-medium">{{ __('dcim::ipam.fields.purpose') }}</label>
            <input id="purpose" type="text" maxlength="255" wire:model="purpose" class="mt-1 block w-full rounded-md border-gray-300">
        </div>
        <div>
            <label for="notes" class="block text-sm font-medium">{{ __('dcim::racks.fields.notes') }}</label>
            <textarea id="notes" rows="2" maxlength="5000" wire:model="notes" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
        </div>
        <p class="text-xs text-gray-500">{{ __('dcim::ipam.network_help') }}</p>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('dcim::ipam.save') }}</button>
    </form>
</div>
