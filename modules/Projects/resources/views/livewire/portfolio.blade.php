<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 underline">{{ __('projects::projects.back') }}</a>
            <h1 class="text-xl font-semibold">{{ __('projects::governance.portfolio') }}</h1>
            <p class="text-sm text-gray-600">{{ __('projects::governance.portfolio_intro') }}</p>
        </div>
        <button type="button" wire:click="export('xlsx')" wire:loading.attr="disabled" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('projects::reports.export.button') }}</button>
    </header>

    @if ($exports->isNotEmpty())
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @foreach ($exports as $export)
                <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{{ __('projects::reports.export.of', ['date' => $export->created_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
                    @if ($export->isDownloadable())
                        <span class="text-gray-500"><a href="{{ route('core.exports.download', $export) }}" class="font-medium text-blue-700 underline">{{ __('core::exports.download') }}</a> · {{ __('core::exports.expires', ['date' => $export->expires_at?->timezone($timezone)->format('Y-m-d H:i')]) }}</span>
                    @else
                        <span class="text-gray-500">{{ $export->status->label() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

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
