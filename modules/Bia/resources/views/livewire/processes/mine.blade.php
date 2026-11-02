<div class="mx-auto max-w-3xl space-y-6">
    <header class="space-y-1">
        <h1 class="text-xl font-semibold">{{ __('bia::processes.mine.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('bia::processes.mine.intro') }}</p>
    </header>

    <ul class="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-gray-200">
        @forelse ($processes as $process)
            @php($assessment = $open->get($process->id))
            <li wire:key="process-{{ $process->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                    <a href="{{ route('bia.processes.show', $process) }}" class="font-medium text-blue-700 underline">{{ $process->name }}</a>
                    <p class="text-gray-500">{{ $process->department?->name }}</p>
                </div>
                <div class="text-right text-xs">
                    @if ($assessment?->status === \Modules\Bia\Domain\Enums\AssessmentStatus::Returned)
                        <a href="{{ route('bia.assessments.show', $assessment) }}" class="font-medium text-amber-700 underline">{{ __('bia::processes.mine.returned') }}</a>
                    @elseif ($assessment?->status === \Modules\Bia\Domain\Enums\AssessmentStatus::Submitted)
                        <span class="text-gray-500">{{ __('bia::processes.mine.submitted') }}</span>
                    @elseif ($assessment)
                        <a href="{{ route('bia.assessments.show', $assessment) }}" class="font-medium text-blue-700 underline">{{ __('bia::processes.mine.to_start') }}</a>
                    @elseif ($process->approved_at === null)
                        <span class="font-medium text-amber-700">{{ __('bia::processes.mine.to_start') }}</span>
                    @elseif ($process->next_review_on !== null && $process->next_review_on->toDateString() <= $today)
                        <span class="font-medium text-amber-700">{{ __('bia::processes.mine.due') }}</span>
                    @else
                        <span class="text-green-700">{{ __('bia::processes.mine.up_to_date') }}</span>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('bia::processes.mine.empty') }}</li>
        @endforelse
    </ul>
</div>
