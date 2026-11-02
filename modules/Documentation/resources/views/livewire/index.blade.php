<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('documentation::docs.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('documentation::docs.intro') }}</p>
        </div>
        @can('create', \Modules\Documentation\Domain\Models\Space::class)
            <a href="{{ route('documentation.spaces.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('documentation::docs.spaces.new') }}</a>
        @endcan
    </div>

    <div>
        <label for="q" class="sr-only">{{ __('documentation::docs.search') }}</label>
        <input id="q" type="search" wire:model.live.debounce.400ms="q" maxlength="200" placeholder="{{ __('documentation::docs.search_placeholder') }}" class="block w-full max-w-xl">
    </div>

    @if ($results !== null)
        <section aria-labelledby="results-heading" class="space-y-3">
            <h2 id="results-heading" class="text-sm font-semibold">{{ trans_choice('documentation::docs.results', $results->total(), ['count' => $results->total()]) }}</h2>
            <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 ring-1 ring-gray-200">
                @forelse ($results as $result)
                    <li wire:key="result-{{ $result->id }}" class="space-y-1 py-3">
                        <a href="{{ route('documentation.pages.show', $result) }}" class="font-medium text-blue-700 underline">{{ $result->displayTitle() }}</a>
                        <span class="text-xs text-gray-500">{{ $result->space->name }}</span>
                        <x-documentation::extract :text="$result->getAttribute('extract')" />
                    </li>
                @empty
                    <li class="py-3 text-sm text-gray-500">{{ __('documentation::docs.no_results') }}</li>
                @endforelse
            </ul>
            {{ $results->links() }}
        </section>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <section aria-labelledby="spaces-heading" class="space-y-3 lg:col-span-2">
                <h2 id="spaces-heading" class="text-sm font-semibold">{{ __('documentation::docs.spaces.title') }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    @forelse ($spaces as $space)
                        <li wire:key="space-{{ $space->id }}" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                            <a href="{{ route('documentation.spaces.show', $space) }}" class="font-semibold text-blue-700 underline">{{ $space->name }}</a>
                            <p class="text-xs text-gray-500">{{ $space->visibility->label() }} · {{ trans_choice('documentation::docs.spaces.pages_count', $space->pages_count, ['count' => $space->pages_count]) }}</p>
                            @if ($space->description) <p class="mt-1 text-sm text-gray-600">{{ $space->description }}</p> @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">{{ __('documentation::docs.spaces.none') }}</li>
                    @endforelse
                </ul>
            </section>
            <section aria-labelledby="recent-heading" class="space-y-3">
                <h2 id="recent-heading" class="text-sm font-semibold">{{ __('documentation::docs.recent') }}</h2>
                <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
                    @forelse ($recent as $page)
                        <li wire:key="recent-{{ $page->id }}" class="py-2">
                            <a href="{{ route('documentation.pages.show', $page) }}" class="text-blue-700 underline">{{ $page->displayTitle() }}</a>
                            <span class="block text-xs text-gray-500">{{ $page->space->name }} · {{ $page->editor?->name ?? '—' }} · {{ $page->updated_at?->timezone(config()->string('app.display_timezone'))->format('Y-m-d') }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">{{ __('documentation::docs.pages.none') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif
</div>
