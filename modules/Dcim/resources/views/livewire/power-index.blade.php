<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('dcim::power.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('dcim::power.intro') }}</p>
        </div>
        @can('create', \Modules\Dcim\Domain\Models\Rack::class)
            <a href="{{ route('dcim.power.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('dcim::power.new') }}</a>
        @endcan
    </div>

    @if ($singleFed > 0)
        <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">{{ trans_choice('dcim::power.single_fed_count', $singleFed, ['count' => $singleFed]) }}</p>
    @endif

    @forelse ($rooms as $locationId => $rows)
        <section wire:key="room-{{ $locationId }}" aria-labelledby="power-room-{{ $locationId }}" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="power-room-{{ $locationId }}" class="text-sm font-semibold">{{ $paths[$locationId] ?? '—' }}</h2>
            <ul class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    @php($source = $row['source'])
                    <li wire:key="source-{{ $source->id }}" class="grid gap-2 py-2 sm:grid-cols-2" style="padding-left: {{ $row['depth'] * 1.5 }}rem">
                        <div>
                            <a href="{{ route('dcim.power.show', $source) }}" class="font-medium text-blue-700 underline">{{ $source->name }}</a>
                            <span class="text-xs text-gray-600">{{ $source->kind->label() }}@if ($source->side) · {{ $source->side->label() }}@endif @if ($source->rack) · {{ __('dcim::racks.rack', ['name' => $source->rack->name]) }}@endif</span>
                        </div>
                        <x-dcim::power-load :load="$row['load']" :capacity="$source->capacity_watts" :warn="$warn" />
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="rounded-lg bg-white p-4 text-sm text-gray-600 ring-1 ring-gray-200">{{ __('dcim::power.none') }}</p>
    @endforelse
</div>
