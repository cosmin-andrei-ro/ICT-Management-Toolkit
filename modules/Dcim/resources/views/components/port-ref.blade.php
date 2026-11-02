@props(['port', 'labels'])
{{-- "Equipment · port", linking to the equipment's page. --}}
<a href="{{ route('dcim.equipment.show', $port->mount_id) }}" class="text-blue-700 underline">{{ ($labels[$port->mount->asset_id] ?? null)?->label() ?? __('dcim::racks.unknown_asset') }}</a>
<span class="text-gray-600">· {{ $port->name }}</span>
