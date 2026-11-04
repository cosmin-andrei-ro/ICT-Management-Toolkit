<x-core::layouts.app :title="__('core::tags.page_title', ['name' => $tag->name])">
    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('core::tags.page_title', ['name' => $tag->name]) }}</h1>
            <p class="text-sm text-gray-500">{{ __('core::tags.page_intro') }}</p>
        </div>

        @forelse ($results as $kind => $records)
            <section class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 class="text-sm font-semibold">{{ $kind }}</h2>
                <ul class="mt-2 divide-y divide-gray-100 text-sm">
                    @foreach ($records as $record)
                        <li class="py-2"><a href="{{ $record->url }}" class="text-blue-700 underline">{{ $record->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="text-sm text-gray-500">{{ __('core::tags.page_none') }}</p>
        @endforelse
    </div>
</x-core::layouts.app>
