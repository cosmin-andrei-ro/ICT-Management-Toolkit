{{-- Configuration items of a ticket and what their failure affects (CMDB). --}}
@props(['items', 'editable' => false])

@if ($items['available'])
    <section class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200" aria-labelledby="items-heading">
        <h2 id="items-heading" class="text-sm font-semibold">{{ __('ticketing::ui.items.title') }}</h2>
        <ul class="space-y-1 text-sm">
            @forelse ($items['linked'] as $item)
                <li wire:key="linked-item-{{ $item->id }}" class="flex items-center justify-between gap-2">
                    <span>
                        @can('cmdb.ci.view')
                            <a href="{{ $item->url }}" class="text-blue-700 underline">{{ $item->name }}</a>
                        @else
                            {{ $item->name }}
                        @endcan
                        <span class="text-xs text-gray-500">{{ $item->typeName }} · {{ $item->criticalityLabel }}</span>
                    </span>
                    @if ($editable)
                        <button type="button" wire:click="unlinkItem('{{ $item->id }}')" class="text-xs text-red-700 underline">{{ __('ticketing::ui.items.remove') }}</button>
                    @endif
                </li>
            @empty
                <li class="text-gray-500">{{ __('ticketing::ui.items.none') }}</li>
            @endforelse
        </ul>

        @if ($editable)
            <div class="space-y-1">
                <input type="search" wire:model.live.debounce.300ms="itemSearch" maxlength="200" placeholder="{{ __('ticketing::ui.items.search') }}" aria-label="{{ __('ticketing::ui.items.search') }}" class="block w-full rounded-md border-gray-300 text-sm">
                @error('itemSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @foreach ($items['candidates'] as $candidate)
                    <button type="button" wire:key="candidate-item-{{ $candidate->id }}" wire:click="linkItem('{{ $candidate->id }}')" class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-gray-50">
                        + {{ $candidate->name }} <span class="text-xs text-gray-500">{{ $candidate->typeName }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if ($items['impact'] !== [])
            <div class="space-y-1 border-t border-gray-100 pt-2">
                <p class="text-xs font-medium text-gray-700">{{ __('ticketing::ui.items.impact', ['count' => count($items['impact'])]) }}</p>
                <ul class="space-y-0.5 text-xs">
                    @foreach ($items['impact'] as $impacted)
                        <li wire:key="impacted-{{ $impacted->id }}">
                            {{ $impacted->name }} <span class="text-gray-500">{{ $impacted->typeName }} · {{ $impacted->criticalityLabel }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
