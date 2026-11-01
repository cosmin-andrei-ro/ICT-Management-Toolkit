<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('ticketing::problems.ui.known_errors') }}</h1>
            <p class="text-sm text-gray-600">{{ __('ticketing::problems.ui.known_errors_intro') }}</p>
        </div>
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('ticketing::problems.ui.search_placeholder') }}" aria-label="{{ __('ticketing::ui.search') }}" class="rounded-md border-gray-300">
    </div>

    <ul class="space-y-4">
        @forelse ($knownErrors as $problem)
            <li wire:key="known-error-{{ $problem->id }}" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('ticketing.desk.show', $problem) }}" class="font-semibold text-blue-700 underline"><span class="font-mono">{{ $problem->number }}</span> {{ $problem->title }}</a>
                    <span class="text-xs text-gray-500">{{ trans_choice('ticketing::problems.ui.incident_count', $problem->incidents_count, ['count' => $problem->incidents_count]) }} · {{ $problem->status->label() }}</span>
                </div>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium">{{ __('ticketing::problems.ui.workaround') }}</dt>
                        <dd class="whitespace-pre-line text-gray-800">{{ $problem->problemDetails?->workaround }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium">{{ __('ticketing::problems.ui.root_cause') }}</dt>
                        <dd class="whitespace-pre-line text-gray-600">{{ $problem->problemDetails?->root_cause ?? '—' }}</dd>
                    </div>
                </dl>
            </li>
        @empty
            <li class="text-sm text-gray-500">{{ __('ticketing::problems.ui.empty') }}</li>
        @endforelse
    </ul>

    {{ $knownErrors->links() }}
</div>
