{{-- 5.2: CMDB items the application runs on, linked through the CMDB. --}}
<div class="space-y-2 rounded-md bg-gray-50 p-3">
    <p class="text-sm font-medium">{{ __('applications::sheet.ui.cmdb_items') }}</p>
    @if (! $cmdbAvailable)
        <p class="text-sm text-gray-500">{{ __('applications::sheet.ui.cmdb_off') }}</p>
    @else
        <ul class="space-y-1 text-sm">
            @forelse ($linkedItems as $item)
                <li wire:key="item-{{ $item->id }}" class="flex flex-wrap items-center justify-between gap-2">
                    <span><a href="{{ $item->url }}" class="text-blue-700 underline">{{ $item->name }}</a> <span class="text-xs text-gray-500">{{ $item->typeName }} · {{ $item->criticalityLabel }}</span></span>
                    @if ($editable) <button type="button" wire:click="unlinkItem('{{ $item->id }}')" class="text-xs text-red-700 underline">{{ __('applications::sheet.ui.remove_row') }}</button> @endif
                </li>
            @empty
                <li class="text-gray-500">{{ __('applications::sheet.ui.no_items') }}</li>
            @endforelse
        </ul>
        @if ($editable)
            <input type="search" wire:model.live.debounce.300ms="itemSearch" maxlength="100" placeholder="{{ __('applications::sheet.ui.find_item') }}" aria-label="{{ __('applications::sheet.ui.find_item') }}" class="block w-full text-sm">
            @error('itemSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($itemCandidates !== [])
                <ul class="divide-y divide-gray-100 rounded-md bg-white text-sm ring-1 ring-gray-200">
                    @foreach ($itemCandidates as $candidate)
                        <li wire:key="item-candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-2 px-2 py-1.5">
                            <span>{{ $candidate->name }} <span class="text-xs text-gray-500">{{ $candidate->typeName }}</span></span>
                            <button type="button" wire:click="linkItem('{{ $candidate->id }}')" class="text-xs text-blue-700 underline">{{ __('applications::sheet.ui.link') }}</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="text-xs text-gray-500">{{ __('applications::sheet.ui.cmdb_help') }}</p>
        @endif
    @endif
</div>
