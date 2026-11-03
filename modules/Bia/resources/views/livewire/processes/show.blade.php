<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            @can('viewAny', \Modules\Bia\Domain\Models\BusinessProcess::class)
                <a href="{{ route('bia.processes.index') }}" class="text-sm text-gray-500 underline">{{ __('bia::processes.ui.back') }}</a>
            @else
                <a href="{{ route('bia.mine') }}" class="text-sm text-gray-500 underline">← {{ __('bia::processes.mine.title') }}</a>
            @endcan
            <h1 class="text-xl font-semibold">{{ $process->name }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if ($process->tier) <span class="rounded bg-gray-100 px-2 py-0.5">{{ $process->tier->label() }}</span> @endif
                @if ($process->status === 'retired') <span class="rounded bg-gray-100 px-2 py-0.5">{{ __('bia::processes.statuses.retired') }}</span> @endif
                @if ($process->department) <span class="text-gray-500">{{ $process->department->name }}</span> @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($assessments->isNotEmpty())
                <a href="{{ route('bia.processes.sheet', $process) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bia::sheet.button') }}</a>
            @endif
            @can('assess', $process)
                @php($open = $assessments->first(fn ($assessment) => $assessment->status->isOpen()))
                <button type="button" wire:click="startAssessment" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __($open ? 'bia::processes.ui.continue' : ($assessments->isEmpty() ? 'bia::processes.ui.start' : 'bia::processes.ui.review')) }}</button>
            @endcan
            @can('update', $process)
                <a href="{{ route('bia.processes.edit', $process) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bia::processes.ui.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('bia::processes.ui.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('bia::processes.ui.delete') }}</button>
            @endcan
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="objectives-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="objectives-heading" class="text-sm font-semibold">{{ __('bia::processes.ui.objectives') }}</h2>
            @if ($process->approved_at)
                <dl class="grid grid-cols-3 gap-2 text-sm">
                    <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.mtpd') }}</dt><dd class="font-semibold">{{ \Modules\Core\Domain\Time\Duration::format($process->mtpd_minutes) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.rto') }}</dt><dd class="font-semibold">{{ \Modules\Core\Domain\Time\Duration::format($process->rto_minutes) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.rpo') }}</dt><dd class="font-semibold">{{ \Modules\Core\Domain\Time\Duration::format($process->rpo_minutes) }}</dd></div>
                </dl>
                <p class="text-xs text-gray-500">{{ __('bia::processes.fields.approved_at') }}: {{ $process->approved_at->toDateString() }} · {{ __('bia::processes.fields.next_review') }}: <span @class(['font-medium text-amber-700' => $process->next_review_on?->isPast()])>{{ $process->next_review_on?->toDateString() }}</span></p>
            @else
                <p class="text-sm text-gray-500">{{ __('bia::processes.ui.not_assessed') }}</p>
            @endif
            <p class="border-t border-gray-100 pt-2 text-xs text-gray-500">{{ __('bia::processes.ui.glossary') }}</p>
        </section>

        <section aria-labelledby="details-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200 lg:col-span-2">
            <h2 id="details-heading" class="text-sm font-semibold">{{ __('bia::processes.ui.details') }}</h2>
            <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.owner') }}</dt><dd>{{ $process->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.deputy') }}</dt><dd>{{ $process->deputy?->name ?? '—' }}</dd></div>
                @if ($process->peak_periods)
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.peak_periods') }}</dt><dd class="whitespace-pre-line">{{ $process->peak_periods }}</dd></div>
                @endif
                @if ($process->description)
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.description') }}</dt><dd class="whitespace-pre-line">{{ $process->description }}</dd></div>
                @endif
            </dl>
        </section>
    </div>

    <section aria-labelledby="versions-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="versions-heading" class="text-sm font-semibold">{{ __('bia::processes.ui.versions') }}</h2>
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($assessments as $assessment)
                <li wire:key="assessment-{{ $assessment->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <a href="{{ route('bia.assessments.show', $assessment) }}" class="text-blue-700 underline">{{ __('bia::processes.ui.version', ['version' => $assessment->version]) }}</a>
                    <span class="flex items-center gap-2 text-xs text-gray-500">
                        <x-bia::assessment-status :status="$assessment->status" />
                        {{ $assessment->decided_at?->toDateString() ?? $assessment->updated_at?->toDateString() }}
                        @if ($assessment->decider) · {{ $assessment->decider->name }} @endif
                    </span>
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('bia::processes.ui.no_versions') }}</li>
            @endforelse
        </ul>
    </section>

    <x-core::record-history :record="$process" :can-change="auth()->user()->can('update', $process)" />
</div>
