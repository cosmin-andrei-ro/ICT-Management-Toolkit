<x-core::layouts.app :title="__('core::search.title')">
    <div class="space-y-4">
        <h1 class="text-xl font-semibold">{{ __('core::search.title') }}</h1>

        @if ($term === '')
            <p class="text-sm text-gray-500">{{ __('core::search.empty') }}</p>
        @elseif ($tooShort)
            <p class="text-sm text-gray-500">{{ __('core::search.too_short') }}</p>
        @elseif ($results === [])
            <p class="text-sm text-gray-500">{{ __('core::search.none', ['term' => $term]) }}</p>
        @else
            <p class="text-sm text-gray-500">{{ __('core::search.results_for', ['term' => $term]) }}</p>

            @foreach ($results as $kind => $records)
                <section class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                    <h2 class="text-sm font-semibold">{{ $kind }}</h2>
                    <ul class="mt-2 divide-y divide-gray-100 text-sm">
                        @foreach ($records as $record)
                            <li class="flex items-center justify-between gap-2 py-2">
                                <a href="{{ $record->url }}" class="text-blue-700 underline">{{ $record->title }}</a>
                                @if ($record->status !== null) <span class="text-xs text-gray-500">{{ $record->status }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </div>
</x-core::layouts.app>
