<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('dcim::racks.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('dcim::racks.intro') }}</p>
        </div>
        @can('create', \Modules\Dcim\Domain\Models\Rack::class)
            <a href="{{ route('dcim.racks.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('dcim::racks.new') }}</a>
        @endcan
    </div>

    @forelse ($rooms as $locationId => $racks)
        <section wire:key="room-{{ $locationId }}" aria-labelledby="room-{{ $locationId }}" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="room-{{ $locationId }}" class="text-sm font-semibold">{{ $paths[$locationId] ?? '—' }}</h2>
                @can('create', \Modules\Dcim\Domain\Models\Rack::class)
                    <a href="{{ route('dcim.racks.create', ['location' => $locationId]) }}" class="text-xs font-medium text-blue-700 underline">{{ __('dcim::racks.new_here') }}</a>
                @endcan
            </div>
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($racks as $rack)
                    @php($used = $rack->usedUnits())
                    @php($percent = (int) round($used / max(1, $rack->height_u) * 100))
                    <li wire:key="rack-{{ $rack->id }}" class="rounded-lg p-3 ring-1 ring-gray-200">
                        <a href="{{ route('dcim.racks.show', $rack) }}" class="font-medium text-blue-700 underline">{{ $rack->name }}</a>
                        <p class="text-xs text-gray-600">{{ collect([$rack->row ? __('dcim::racks.row_value', ['row' => $rack->row]) : null, $rack->position ? __('dcim::racks.position_value', ['position' => $rack->position]) : null])->filter()->implode(' · ') ?: ' ' }}</p>
                        <div class="mt-2 h-2 rounded bg-gray-100" role="img" aria-label="{{ __('dcim::racks.occupancy', ['used' => $used, 'height' => $rack->height_u, 'percent' => $percent]) }}">
                            <div @class(['h-2 rounded', 'bg-blue-600' => $percent < 85, 'bg-amber-500' => $percent >= 85]) style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-gray-600">{{ __('dcim::racks.occupancy', ['used' => $used, 'height' => $rack->height_u, 'percent' => $percent]) }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="rounded-lg bg-white p-4 text-sm text-gray-600 ring-1 ring-gray-200">{{ __('dcim::racks.none') }}</p>
    @endforelse
</div>
