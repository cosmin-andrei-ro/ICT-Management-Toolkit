<x-core::layouts.manual :contents="$contents">
    <h1 class="text-2xl font-semibold">{{ __('core::manual.title') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('core::manual.intro') }}</p>

    <div class="mt-8 grid gap-6 sm:grid-cols-3">
        @foreach ($contents as $audience => $pages)
            <section class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 class="font-semibold">{{ \Modules\Core\Domain\Manual\ManualAudience::from($audience)->label() }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($pages as $page)
                        <li><a href="{{ route('manual.show', $page->slug) }}" class="text-blue-700 underline">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-core::layouts.manual>
