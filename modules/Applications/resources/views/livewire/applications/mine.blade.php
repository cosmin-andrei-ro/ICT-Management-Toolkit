<div class="space-y-6">
    <header>
        <h1 class="text-xl font-semibold">{{ __('applications::applications.mine.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('applications::applications.mine.intro') }}</p>
    </header>
    <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
        @forelse ($applications as $application)
            <li wire:key="application-{{ $application->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                <a href="{{ route('applications.show', $application) }}" class="text-blue-700 underline">{{ $application->name }}</a>
                <span class="text-xs text-gray-500">{{ $application->current_version ? 'v'.$application->current_version : __('applications::applications.ui.not_documented') }} · {{ __('applications::applications.fields.next_review') }}: {{ $application->next_review_on?->toDateString() ?? '—' }}</span>
            </li>
        @empty
            <li class="py-2 text-gray-500">{{ __('applications::applications.ui.none') }}</li>
        @endforelse
    </ul>
</div>
