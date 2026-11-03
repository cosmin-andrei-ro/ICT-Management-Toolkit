@php($title = $project->code.' · '.$project->name)
@php($money = fn (?float $value): string => $value === null ? '—' : \Illuminate\Support\Number::format($value, 2).' '.($budget->currency ?? ''))
<x-core::printable :title="$title" :pdf="$pdf" :styles="$styles ?? ''" :back-url="route('projects.show', $project)" :back-label="'← '.$title" :pdf-url="$pdfAvailable ? route('projects.report.pdf', $project) : null">
    <main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ __('projects::reports.sheet.kicker') }}</p>
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            <p class="text-gray-600">
                {{ $project->kind->label() }} · {{ $project->status->label() }}
                · {{ __('projects::projects.fields.manager') }}: {{ $project->manager?->name ?? '—' }}
                · {{ __('projects::projects.fields.sponsor') }}: {{ $project->sponsor?->name ?? '—' }}
                @if ($project->planned_start || $project->planned_end) · {{ $project->planned_start?->toDateString() ?? '…' }} → {{ $project->planned_end?->toDateString() ?? '…' }} @endif
            </p>
            <p class="text-gray-600">{{ __('projects::reports.sheet.printed', ['date' => now()->timezone(config()->string('app.display_timezone'))->format('Y-m-d H:i')]) }}</p>
        </header>

        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ __('projects::projects.phases_heading') }}</h2>
            <table class="min-w-full text-left">
                <tbody class="divide-y divide-gray-200">
                    @foreach ($project->phases as $phase)
                        <tr>
                            <td class="py-1 pr-3 font-medium">{{ $phase->position }}. {{ $phase->key->label() }}</td>
                            <td class="py-1 pr-3">{{ $phase->status->label() }}</td>
                            <td class="py-1 pr-3 text-gray-600">{{ __('projects::reports.sheet.planned', ['start' => $phase->planned_start?->toDateString() ?? '…', 'end' => $phase->planned_end?->toDateString() ?? '…']) }}</td>
                            <td class="py-1 text-gray-600">{{ $phase->completed_at ? __('projects::reports.sheet.completed', ['date' => $phase->completed_at->toDateString()]) : ($phase->started_at ? __('projects::reports.sheet.started', ['date' => $phase->started_at->toDateString()]) : '') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($pending) <p class="font-medium text-amber-800">{{ __('projects::governance.gate_waiting') }}</p> @endif
        </section>

        <section class="space-y-2 break-inside-avoid">
            <h2 class="text-base font-semibold">{{ __('projects::reports.sheet.status') }}</h2>
            @if ($report)
                <p class="flex flex-wrap gap-1 text-xs">
                    {{ $report->reported_on->toDateString() }} ·
                    @foreach (['overall', 'schedule', 'budget', 'scope'] as $dimension)
                        <x-projects::rag :rag="$report->{$dimension}" :label="__('projects::governance.dimensions.'.$dimension)" />
                    @endforeach
                </p>
                <p><span class="font-medium">{{ __('projects::governance.fields.achievements') }}:</span> <span class="whitespace-pre-line">{{ $report->achievements }}</span></p>
                <p><span class="font-medium">{{ __('projects::governance.fields.nextSteps') }}:</span> <span class="whitespace-pre-line">{{ $report->next_steps }}</span></p>
                @if ($report->issues) <p><span class="font-medium">{{ __('projects::governance.fields.issues') }}:</span> <span class="whitespace-pre-line">{{ $report->issues }}</span></p> @endif
            @else
                <p class="text-gray-600">{{ __('projects::governance.no_reports') }}</p>
            @endif
        </section>

        <section class="space-y-2 break-inside-avoid">
            <h2 class="text-base font-semibold">{{ __('projects::budget.heading') }}</h2>
            <p>{{ __('projects::budget.approved') }}: <strong>{{ $money($budget->budget) }}</strong> · {{ __('projects::budget.spent') }}: <strong>{{ $money($budget->spent) }}</strong> · {{ __('projects::budget.remaining') }}: <strong @class(['text-red-700' => $budget->isOver()])>{{ $money($budget->remaining) }}</strong>@if ($budget->percent !== null) ({{ $budget->percent }}%)@endif</p>
            @if ($budget->spent != 0)
                <p class="text-gray-600">{{ collect(\Modules\Projects\Domain\Enums\CostCategory::cases())->filter(fn ($category) => $budget->byCategory[$category->value] != 0)->map(fn ($category) => $category->label().': '.$money($budget->byCategory[$category->value]))->implode(' · ') }}</p>
            @endif
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ __('projects::deliverables.heading') }}</h2>
            @if ($deliverables->isEmpty())
                <p class="text-gray-600">{{ __('projects::deliverables.none') }}</p>
            @else
                <table class="min-w-full text-left">
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($deliverables as $deliverable)
                            <tr>
                                <td class="py-1 pr-3">{{ $deliverable->title }} @if ($deliverable->linked_title) <span class="text-gray-500">· {{ $deliverable->linked_title }}</span> @endif</td>
                                <td class="py-1 pr-3 text-gray-600">{{ $deliverable->phase->key->label() }} · {{ $deliverable->required ? __('projects::deliverables.required') : __('projects::deliverables.optional') }}</td>
                                <td class="py-1">{{ $deliverable->isDelivered() ? __('projects::reports.sheet.delivered', ['date' => $deliverable->delivered_at?->toDateString()]) : __('projects::deliverables.not_delivered') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if ($checklist !== [])
                <p class="font-medium">{{ __('projects::deliverables.checklist_heading') }}</p>
                <ul class="list-none">
                    @foreach ($checklist as $check => $done)
                        <li>{{ $done ? '☑' : '☐' }} {{ __('projects::deliverables.items.'.$check) }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($blockers !== [])
                <p class="font-medium text-amber-800">{{ __('projects::deliverables.blockers') }}</p>
                <ul class="list-disc pl-5 text-amber-900">
                    @foreach ($blockers as $blocker) <li>{{ $blocker }}</li> @endforeach
                </ul>
            @endif
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ __('projects::projects.tasks') }}</h2>
            <p>{{ __('projects::projects.progress', ['done' => $tasks['done'], 'total' => $tasks['total']]) }}</p>
            @if ($overdue->isNotEmpty())
                <p class="font-medium text-red-700">{{ trans_choice('projects::reports.sheet.overdue', $overdue->count(), ['count' => $overdue->count()]) }}</p>
                <ul class="list-disc pl-5">
                    @foreach ($overdue as $task)
                        <li>{{ $task->title }} — {{ $task->assignee?->name ?? __('projects::projects.unassigned') }}, {{ $task->due_on?->toDateString() }} ({{ $task->phase->key->label() }})</li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($gates->isNotEmpty())
            <section class="space-y-2">
                <h2 class="text-base font-semibold">{{ __('projects::reports.sheet.decisions') }}</h2>
                <ul class="list-disc pl-5 text-gray-700">
                    @foreach ($gates as $gate)
                        <li>{{ $gate->decided_at?->toDateString() }} · {{ $gate->closesProject() ? __('projects::governance.closing') : $gate->fromPhase->key->label().' → '.$gate->toPhase?->key->label() }} · {{ $gate->status->label() }} {{ __('projects::governance.by', ['name' => $gate->decider?->name ?? '—']) }}@if ($gate->decision_note) — “{{ $gate->decision_note }}”@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</x-core::printable>
