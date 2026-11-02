<div class="space-y-6">
    <header>
        <h1 class="text-xl font-semibold">{{ __('risk::risks.mine.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('risk::risks.mine.intro') }}</p>
    </header>
    <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
        @forelse ($risks as $risk)
            @php($score = $risk->inherentScore())
            <li wire:key="risk-{{ $risk->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                <a href="{{ route('risk.risks.show', $risk) }}" class="text-blue-700 underline">{{ $risk->code() }} · {{ $risk->title }}</a>
                <x-risk::level :level="$score !== null ? $settings->level($score) : null" :score="$score" />
            </li>
        @empty
            <li class="py-2 text-gray-500">{{ __('risk::risks.ui.none') }}</li>
        @endforelse
    </ul>
</div>
