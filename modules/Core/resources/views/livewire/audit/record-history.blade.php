<section class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h2 class="font-semibold">{{ __('core::audit.history.title') }}</h2>
            <p class="text-sm text-gray-600">{{ __('core::audit.history.intro') }}</p>
        </div>
        <button type="button" wire:click="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">
            {{ $open ? __('core::audit.history.hide') : __('core::audit.history.show') }}
        </button>
    </div>

    @if ($entries !== null)
        @if ($entries->isEmpty())
            <p class="mt-4 text-sm text-gray-500">{{ __('core::audit.history.empty') }}</p>
        @else
            <ol class="mt-4 divide-y divide-gray-100">
                @foreach ($entries as $entry)
                    <li wire:key="history-{{ $entry->id }}" class="py-3 text-sm">
                        <p>
                            <span class="font-medium">{{ $entry->event->label() }}</span>
                            <span class="text-gray-600">· {{ $entry->user?->name ?? __('core::audit.system') }} · {{ $entry->created_at->timezone($timezone)->format('Y-m-d H:i') }}</span>
                        </p>
                        @php($rows = $changes->rows($entry))
                        @if ($rows !== [])
                            <div class="mt-2 overflow-x-auto">
                                <table class="w-full table-fixed text-xs">
                                    <thead class="text-left text-gray-500">
                                        <tr>
                                            <th class="w-1/4 py-1 pr-3 font-medium">{{ __('core::audit.fields.field') }}</th>
                                            <th class="w-3/8 py-1 pr-3 font-medium">{{ __('core::audit.fields.old') }}</th>
                                            <th class="py-1 font-medium">{{ __('core::audit.fields.new') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr class="align-top">
                                                <td class="py-1 pr-3 text-gray-700">{{ $row['field'] }}</td>
                                                <td class="py-1 pr-3 break-words text-gray-500">{{ $row['old'] ?? '' }}</td>
                                                <td class="py-1 break-words">{{ $row['new'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
            <div class="mt-2">{{ $entries->links(data: ['scrollTo' => false]) }}</div>
        @endif
    @endif
</section>
