<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 underline">{{ __('projects::projects.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __('projects::governance.portfolio') }}</h1>
        <p class="text-sm text-gray-600">{{ __('projects::governance.portfolio_intro') }}</p>
    </header>

    <dl class="grid gap-3 sm:grid-cols-4">
        @foreach (\Modules\Projects\Domain\Enums\Rag::cases() as $rag)
            <div wire:key="count-{{ $rag->value }}" class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
                <dt class="text-xs"><x-projects::rag :rag="$rag" /></dt>
                <dd class="text-2xl font-semibold">{{ $counts[$rag->value] }}</dd>
            </div>
        @endforeach
        <div class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
            <dt class="text-xs text-gray-600">{{ __('projects::governance.unreported') }}</dt>
            <dd class="text-2xl font-semibold">{{ $unreported }}</dd>
        </div>
    </dl>

    <div class="overflow-x-auto rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.name') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.phase') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::governance.latest_status') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.manager') }} / {{ __('projects::projects.fields.sponsor') }}</th>
                    <th scope="col" class="py-2 font-medium">{{ __('projects::projects.fields.planned_end') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($projects as $project)
                    @php($report = $latest[$project->id] ?? null)
                    <tr wire:key="project-{{ $project->id }}">
                        <td class="py-2 pr-4"><a href="{{ route('projects.show', $project) }}" class="font-medium text-blue-700 underline">{{ $project->code }} · {{ $project->name }}</a>@if ($project->status === \Modules\Projects\Domain\Enums\ProjectStatus::OnHold) <span class="text-xs text-gray-500">· {{ $project->status->label() }}</span>@endif</td>
                        <td class="py-2 pr-4">
                            {{ $project->currentPhase()?->key->label() ?? '—' }}
                            @if ($project->gate_pending) <span class="block text-xs font-medium text-amber-800">{{ __('projects::governance.gate_waiting') }}</span> @endif
                        </td>
                        <td class="py-2 pr-4">
                            @if ($report)
                                <x-projects::rag :rag="$report->overall" />
                                <span @class(['block text-xs', 'text-amber-800 font-medium' => $report->reported_on->lt(today()->subDays($every)), 'text-gray-500' => ! $report->reported_on->lt(today()->subDays($every))])>{{ $report->reported_on->toDateString() }}</span>
                            @else
                                <span class="text-xs text-gray-500">{{ __('projects::governance.never_reported') }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4">{{ $project->manager?->name ?? '—' }} <span class="block text-xs text-gray-500">{{ $project->sponsor?->name ?? '—' }}</span></td>
                        <td @class(['py-2', 'font-medium text-red-700' => $project->planned_end?->isPast()])>{{ $project->planned_end?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-600">{{ __('projects::projects.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
