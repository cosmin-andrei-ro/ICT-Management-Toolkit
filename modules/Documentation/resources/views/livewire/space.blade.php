<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('documentation.index') }}" class="text-sm text-gray-500 underline">{{ __('documentation::docs.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $space->name }}</h1>
            <p class="text-sm text-gray-600">{{ $space->visibility->label() }} · {{ __('documentation::docs.spaces.owners') }}: {{ $space->owners->pluck('name')->join(', ') ?: '—' }}</p>
            @if ($space->description) <p class="max-w-3xl text-sm">{{ $space->description }}</p> @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @can('update', $space)
                <a href="{{ route('documentation.spaces.edit', $space) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('documentation::docs.spaces.edit') }}</a>
            @endcan
            @can('edit', $space)
                <a href="{{ route('documentation.pages.create', $space) }}" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('documentation::docs.pages.new') }}</a>
            @endcan
        </div>
    </header>

    <div>
        <label for="q" class="sr-only">{{ __('documentation::docs.search') }}</label>
        <input id="q" type="search" wire:model.live.debounce.400ms="q" maxlength="200" placeholder="{{ __('documentation::docs.search_space', ['space' => $space->name]) }}" class="block w-full max-w-xl">
    </div>

    @if ($toApprove->isNotEmpty() || $toReview->isNotEmpty())
        <div class="grid gap-4 md:grid-cols-2">
            @if ($toApprove->isNotEmpty())
                <section aria-labelledby="approve-heading" class="rounded-lg bg-amber-50 p-4 text-sm ring-1 ring-amber-200">
                    <h2 id="approve-heading" class="font-semibold">{{ __('documentation::docs.publishing.waiting_list') }}</h2>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($toApprove as $item) <li wire:key="approve-{{ $item->id }}"><a href="{{ route('documentation.pages.show', ['page' => $item, 'draft' => 1]) }}" class="underline">{{ $item->title }}</a></li> @endforeach
                    </ul>
                </section>
            @endif
            @if ($toReview->isNotEmpty())
                <section aria-labelledby="review-heading" class="rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
                    <h2 id="review-heading" class="font-semibold">{{ __('documentation::docs.publishing.review_list') }}</h2>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($toReview as $item) <li wire:key="review-{{ $item->id }}"><a href="{{ route('documentation.pages.show', $item) }}" class="underline">{{ $item->displayTitle() }}</a> <span @class(['text-xs', 'font-semibold text-red-700' => $item->isReviewOverdue($today), 'text-gray-500' => ! $item->isReviewOverdue($today)])>{{ $item->review_on?->toDateString() }}</span></li> @endforeach
                    </ul>
                </section>
            @endif
        </div>
    @endif

    @if ($results !== null)
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 ring-1 ring-gray-200">
            @forelse ($results as $result)
                <li wire:key="result-{{ $result->id }}" class="space-y-1 py-3">
                    <a href="{{ route('documentation.pages.show', $result) }}" class="font-medium text-blue-700 underline">{{ $result->displayTitle() }}</a>
                    <x-documentation::extract :text="$result->getAttribute('extract')" />
                </li>
            @empty
                <li class="py-3 text-sm text-gray-500">{{ __('documentation::docs.no_results') }}</li>
            @endforelse
        </ul>
    @else
        <nav aria-label="{{ __('documentation::docs.pages.tree') }}" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <ul class="space-y-1 text-sm">
                @forelse ($tree as $row)
                    <li wire:key="tree-{{ $row['page']->id }}" style="padding-left: {{ $row['depth'] * 1.25 }}rem">
                        <a href="{{ route('documentation.pages.show', $row['page']) }}" class="text-blue-700 underline">{{ $canEdit ? $row['page']->title : $row['page']->displayTitle() }}</a>
                        @if ($canEdit && ! $row['page']->isPublished()) <span class="text-xs text-amber-700">{{ __('documentation::docs.publishing.unpublished') }}</span>
                        @elseif ($canEdit && $row['page']->hasDraft()) <span class="text-xs text-amber-700">{{ __('documentation::docs.publishing.draft') }}</span> @endif
                        @if ($row['page']->pending_version && $canEdit) <span class="text-xs text-amber-700">{{ __('documentation::docs.publishing.waiting') }}</span> @endif
                    </li>
                @empty
                    <li class="text-gray-500">{{ __('documentation::docs.pages.none') }}</li>
                @endforelse
            </ul>
        </nav>
    @endif

    <x-core::record-history :record="$space" :can-change="auth()->user()->can('update', $space)" />
</div>
