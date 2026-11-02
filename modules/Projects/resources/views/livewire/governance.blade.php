<div class="grid gap-6 lg:grid-cols-2">
    <section aria-labelledby="gate-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="gate-heading" class="text-sm font-semibold">{{ __('projects::governance.gate_heading') }}</h2>
        @error('gate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        @if ($pending)
            <div class="space-y-2 rounded-md bg-amber-50 p-3 text-sm ring-1 ring-amber-200">
                <p class="font-medium">{{ $pending->closesProject() ? __('projects::governance.pending_close', ['name' => $pending->requester?->name ?? '—']) : __('projects::governance.pending_move', ['to' => $pending->toPhase?->key->label(), 'name' => $pending->requester?->name ?? '—']) }}</p>
                @if ($pending->note) <p class="whitespace-pre-line text-gray-700">{{ $pending->note }}</p> @endif
                @if ($isSponsor && $pending->requested_by !== $user->id)
                    <div class="space-y-2">
                        <label for="decisionNote" class="block text-xs font-medium">{{ __('projects::governance.decision_note') }}</label>
                        <textarea id="decisionNote" rows="2" maxlength="5000" wire:model="decisionNote" class="block w-full rounded-md border-gray-300 text-sm"></textarea>
                        @error('decisionNote') <p class="text-red-600">{{ $message }}</p> @enderror
                        <div class="flex gap-2">
                            <button type="button" wire:click="approve" class="rounded-md bg-gray-900 px-3 py-1.5 font-semibold text-white">{{ $pending->closesProject() ? __('projects::governance.approve_close') : __('projects::governance.approve') }}</button>
                            <button type="button" wire:click="reject" class="rounded-md bg-white px-3 py-1.5 font-semibold text-red-700 ring-1 ring-gray-300">{{ __('projects::governance.reject') }}</button>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-600">{{ __('projects::governance.waiting_for', ['name' => $project->sponsor?->name ?? '—']) }}</p>
                @endif
            </div>
        @elseif ($project->isOpen() && $current)
            <p class="text-sm text-gray-600">{{ __('projects::governance.in_phase', ['phase' => $current->key->label()]) }}</p>
            @if ($canManage)
                <form wire:submit="requestGate" class="space-y-2 text-sm" novalidate>
                    <label for="gateNote" class="block text-xs font-medium">{{ __('projects::governance.gate_note') }}</label>
                    <textarea id="gateNote" rows="2" maxlength="5000" wire:model="gateNote" class="block w-full rounded-md border-gray-300 text-sm"></textarea>
                    <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 font-semibold text-white">{{ $next ? __('projects::governance.request_move', ['to' => $next->key->label()]) : __('projects::governance.request_close') }}</button>
                </form>
            @endif
        @else
            <p class="text-sm text-gray-600">{{ __('projects::governance.ended', ['status' => $project->status->label()]) }}</p>
        @endif

        @if ($history->isNotEmpty())
            <ul class="divide-y divide-gray-100 text-xs text-gray-600">
                @foreach ($history as $gate)
                    <li wire:key="gate-{{ $gate->id }}" class="py-1">
                        {{ $gate->decided_at?->toDateString() }} ·
                        {{ $gate->closesProject() ? __('projects::governance.closing') : $gate->fromPhase->key->label().' → '.$gate->toPhase?->key->label() }}
                        · {{ $gate->status->label() }} {{ __('projects::governance.by', ['name' => $gate->decider?->name ?? '—']) }}
                        @if ($gate->decision_note) <span class="block text-gray-500">“{{ $gate->decision_note }}”</span> @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="reports-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="reports-heading" class="text-sm font-semibold">{{ __('projects::governance.reports_heading') }}</h2>
            @if ($canManage && ! $writing)
                <button type="button" wire:click="startReport" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('projects::governance.write_report') }}</button>
            @endif
        </div>
        @php($latest = $reports->first())
        @if ($latest && $project->isOpen() && $latest->reported_on->lt(today()->subDays($everyDays)))
            <p class="text-xs font-medium text-amber-800">{{ __('projects::governance.report_due', ['days' => $everyDays]) }}</p>
        @elseif (! $latest && $project->isOpen())
            <p class="text-xs text-gray-600">{{ __('projects::governance.no_reports') }}</p>
        @endif

        @if ($writing)
            <form wire:submit="saveReport" class="space-y-3 text-sm" novalidate>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (['overall', 'schedule', 'budget', 'scope'] as $dimension)
                        <div wire:key="rag-{{ $dimension }}">
                            <label for="{{ $dimension }}" class="block text-xs font-medium">{{ __('projects::governance.dimensions.'.$dimension) }}</label>
                            <select id="{{ $dimension }}" wire:model="{{ $dimension }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                @foreach ($ragOptions as $option)
                                    <option wire:key="{{ $dimension }}-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                @foreach (['achievements' => true, 'nextSteps' => true, 'issues' => false] as $field => $required)
                    <div wire:key="field-{{ $field }}">
                        <label for="{{ $field }}" class="block text-xs font-medium">{{ __('projects::governance.fields.'.$field) }} @if ($required)<span class="text-red-600" aria-hidden="true">*</span>@endif</label>
                        <textarea id="{{ $field }}" rows="3" maxlength="5000" wire:model="{{ $field }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                        @error($field) <p class="text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 font-semibold text-white">{{ __('projects::governance.save_report') }}</button>
                    <button type="button" wire:click="cancelReport" class="rounded-md bg-white px-3 py-1.5 ring-1 ring-gray-300">{{ __('projects::projects.cancel') }}</button>
                </div>
            </form>
        @endif

        <ol class="space-y-3 text-sm">
            @foreach ($reports as $report)
                <li wire:key="report-{{ $report->id }}" class="space-y-1 border-t border-gray-100 pt-2">
                    <p class="flex flex-wrap items-center gap-1 text-xs text-gray-600">
                        {{ $report->reported_on->toDateString() }} · {{ $report->author?->name ?? '—' }}
                        <x-projects::rag :rag="$report->overall" :label="__('projects::governance.dimensions.overall')" />
                        <x-projects::rag :rag="$report->schedule" :label="__('projects::governance.dimensions.schedule')" />
                        <x-projects::rag :rag="$report->budget" :label="__('projects::governance.dimensions.budget')" />
                        <x-projects::rag :rag="$report->scope" :label="__('projects::governance.dimensions.scope')" />
                    </p>
                    <p><span class="font-medium">{{ __('projects::governance.fields.achievements') }}:</span> <span class="whitespace-pre-line">{{ $report->achievements }}</span></p>
                    <p><span class="font-medium">{{ __('projects::governance.fields.nextSteps') }}:</span> <span class="whitespace-pre-line">{{ $report->next_steps }}</span></p>
                    @if ($report->issues) <p><span class="font-medium">{{ __('projects::governance.fields.issues') }}:</span> <span class="whitespace-pre-line">{{ $report->issues }}</span></p> @endif
                </li>
            @endforeach
        </ol>
    </section>
</div>
