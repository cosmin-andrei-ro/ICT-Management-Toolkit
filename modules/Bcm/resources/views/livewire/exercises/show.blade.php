@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
@php($inProgress = $exercise->status === \Modules\Bcm\Domain\Enums\ExerciseStatus::InProgress)
@php($recording = $editable && $inProgress)
<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('bcm.plans.show', $plan) }}" class="text-sm text-gray-500 underline print:hidden">← {{ $plan->name }}</a>
            <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold">{{ $exercise->title }} <x-bcm::exercise-status :status="$exercise->status" /></h1>
            <p class="text-xs text-gray-500">
                {{ $plan->type->label() }} · {{ $plan->name }}@if ($exercise->version) · {{ __('bcm::plans.ui.version', ['version' => $exercise->version->version]) }}@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 print:hidden">
            @if ($exercise->status === \Modules\Bcm\Domain\Enums\ExerciseStatus::Completed)
                <a href="{{ route('bcm.exercises.report', $exercise) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::exercises.ui.print') }}</a>
            @endif
            @if ($editable)
                @if ($exercise->status === \Modules\Bcm\Domain\Enums\ExerciseStatus::Planned)
                    <button type="button" wire:click="start" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('bcm::exercises.ui.start') }}</button>
                @endif
                <button type="button" wire:click="cancelExercise" wire:confirm="{{ __('bcm::exercises.ui.cancel_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('bcm::exercises.ui.cancel') }}</button>
            @endif
        </div>
    </header>

    @error('steps') <p class="rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

    <section aria-labelledby="details-heading" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="details-heading" class="sr-only">{{ __('bcm::exercises.ui.details') }}</h2>
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.type') }}</dt><dd>{{ $exercise->type->label() }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.scenario') }}</dt><dd>{{ $exercise->scenario ? __('bcm::plans.scenarios.'.$exercise->scenario->value) : '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.scheduled_on') }}</dt><dd>{{ $exercise->scheduled_on->toDateString() }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.facilitator') }}</dt><dd>{{ $exercise->facilitator?->name ?? '—' }}</dd></div>
            @if ($exercise->started_at) <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.started_at') }}</dt><dd>{{ $exercise->started_at->toDateTimeString('minute') }}</dd></div> @endif
            @if ($exercise->completed_at) <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.completed_at') }}</dt><dd>{{ $exercise->completed_at->toDateTimeString('minute') }}</dd></div> @endif
            @if ($exercise->outcome) <div><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.outcome') }}</dt><dd class="font-medium">{{ $exercise->outcome->label() }}</dd></div> @endif
            @if ($exercise->participants) <div class="sm:col-span-2 lg:col-span-4"><dt class="text-xs text-gray-500">{{ __('bcm::exercises.fields.participants') }}</dt><dd class="whitespace-pre-line">{{ $exercise->participants }}</dd></div> @endif
        </dl>
    </section>

    @if ($exercise->status === \Modules\Bcm\Domain\Enums\ExerciseStatus::Planned)
        <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-700">{{ __('bcm::exercises.ui.planned_help') }}</p>
    @elseif ($exercise->version)
        {{-- Measured recovery time against the plan's estimate and the business's requirement. --}}
        <p @class(['rounded-md p-3 text-sm',
            'bg-red-50 text-red-800' => $measured !== null && $rto !== null && $measured > $rto,
            'bg-green-50 text-green-800' => $measured !== null && $rto !== null && $measured <= $rto,
            'bg-gray-50 text-gray-700' => $measured === null || $rto === null])>
            @if ($measured === null)
                {{ __('bcm::exercises.ui.no_measure') }}
            @else
                {{ __('bcm::exercises.ui.measured', ['value' => $duration($measured), 'estimate' => $duration($estimate)]) }}
                @if ($rto !== null)
                    {{ __($measured > $rto ? 'bcm::exercises.ui.over_rto' : 'bcm::exercises.ui.within_rto', ['value' => $duration($rto)]) }}
                @endif
            @endif
        </p>

        <section aria-labelledby="steps-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="steps-heading" class="text-sm font-semibold">{{ __('bcm::exercises.ui.steps') }}</h2>
            @if ($steps->isEmpty())
                <p class="text-sm text-gray-500">{{ __('bcm::exercises.ui.no_steps') }}</p>
            @else
                <ol class="divide-y divide-gray-100 text-sm">
                    @foreach ($steps as $step)
                        <li wire:key="step-{{ $step->id }}" class="grid gap-2 py-3 lg:grid-cols-12">
                            <div class="lg:col-span-5">
                                <p><span class="font-medium">{{ $step->position }}.</span> <span class="text-xs uppercase text-gray-500">{{ $step->phase->label() }}</span> {{ $step->action }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ __('bcm::exercises.ui.estimated', ['value' => $duration($step->estimated_minutes)]) }}
                                    @if ($step->configuration_item_id && isset($profiles[$step->configuration_item_id])) · <a href="{{ $profiles[$step->configuration_item_id]->url }}" class="underline">{{ $profiles[$step->configuration_item_id]->name }}</a> @endif
                                </p>
                            </div>
                            @if ($recording)
                                <div class="lg:col-span-2">
                                    <label for="result-{{ $step->id }}" class="sr-only">{{ __('bcm::exercises.fields.result') }}</label>
                                    <select id="result-{{ $step->id }}" wire:model="stepResults.{{ $step->id }}.result" class="block w-full">
                                        @foreach ($results as $result) <option value="{{ $result->value }}" wire:key="result-{{ $step->id }}-{{ $result->value }}">{{ $result->label() }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="flex gap-1 lg:col-span-2">
                                    <label for="value-{{ $step->id }}" class="sr-only">{{ __('bcm::exercises.fields.actual') }}</label>
                                    <input id="value-{{ $step->id }}" type="number" min="0" wire:model="stepResults.{{ $step->id }}.value" placeholder="{{ __('bcm::exercises.fields.actual') }}" class="block w-20">
                                    <label for="unit-{{ $step->id }}" class="sr-only">{{ __('bcm::exercises.fields.unit') }}</label>
                                    <select id="unit-{{ $step->id }}" wire:model="stepResults.{{ $step->id }}.unit" class="block">
                                        @foreach ($units as $unit) <option value="{{ $unit }}" wire:key="unit-{{ $step->id }}-{{ $unit }}">{{ __('core::durations.unit_names.'.$unit) }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="lg:col-span-3">
                                    <label for="notes-{{ $step->id }}" class="sr-only">{{ __('bcm::exercises.fields.notes') }}</label>
                                    <input id="notes-{{ $step->id }}" type="text" maxlength="2000" wire:model="stepResults.{{ $step->id }}.notes" placeholder="{{ __('bcm::exercises.fields.notes') }}" class="block w-full">
                                </div>
                            @else
                                <div class="lg:col-span-7">
                                    <p @class(['font-medium', 'text-green-700' => $step->result === \Modules\Bcm\Domain\Enums\StepResult::Passed, 'text-amber-700' => $step->result === \Modules\Bcm\Domain\Enums\StepResult::Issues, 'text-red-700' => $step->result === \Modules\Bcm\Domain\Enums\StepResult::Failed])>
                                        {{ $step->result->label() }}@if ($step->actual_minutes !== null) · {{ $duration($step->actual_minutes) }}@endif
                                    </p>
                                    @if ($step->notes) <p class="text-xs text-gray-600">{{ $step->notes }}</p> @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        @if ($exerciseItems->isNotEmpty())
            <section aria-labelledby="items-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 id="items-heading" class="text-sm font-semibold">{{ __('bcm::exercises.ui.items') }}</h2>
                <p class="text-xs text-gray-500">{{ __('bcm::exercises.ui.items_help') }}</p>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($exerciseItems as $item)
                        @php($profile = $profiles[$item->configuration_item_id] ?? null)
                        <li wire:key="item-{{ $item->id }}" class="grid gap-2 py-3 lg:grid-cols-12">
                            <div class="lg:col-span-4">
                                @if ($profile) <a href="{{ $profile->url }}" class="underline">{{ $profile->name }}</a> <span class="text-xs text-gray-500">{{ $profile->typeName }}</span> @else <span class="text-gray-500">{{ __('bcm::exercises.ui.unknown_item') }}</span> @endif
                                @if ($profile) <p class="text-xs text-gray-500">{{ __('bcm::exercises.ui.declared', ['value' => $duration($profile->recoveryTimeMinutes)]) }}</p> @endif
                            </div>
                            @if ($recording)
                                <div class="lg:col-span-3">
                                    <label for="recovered-{{ $item->id }}" class="sr-only">{{ __('bcm::exercises.fields.recovered') }}</label>
                                    <select id="recovered-{{ $item->id }}" wire:model="itemResults.{{ $item->id }}.recovered" class="block w-full">
                                        <option value="">{{ __('bcm::exercises.recovered.untested') }}</option>
                                        <option value="yes">{{ __('bcm::exercises.recovered.yes') }}</option>
                                        <option value="no">{{ __('bcm::exercises.recovered.no') }}</option>
                                    </select>
                                </div>
                                <div class="flex gap-1 lg:col-span-2">
                                    <label for="item-value-{{ $item->id }}" class="sr-only">{{ __('bcm::exercises.fields.measured') }}</label>
                                    <input id="item-value-{{ $item->id }}" type="number" min="0" wire:model="itemResults.{{ $item->id }}.value" placeholder="{{ __('bcm::exercises.fields.measured') }}" class="block w-20">
                                    <label for="item-unit-{{ $item->id }}" class="sr-only">{{ __('bcm::exercises.fields.unit') }}</label>
                                    <select id="item-unit-{{ $item->id }}" wire:model="itemResults.{{ $item->id }}.unit" class="block">
                                        @foreach ($units as $unit) <option value="{{ $unit }}" wire:key="item-unit-{{ $item->id }}-{{ $unit }}">{{ __('core::durations.unit_names.'.$unit) }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="lg:col-span-3">
                                    <label for="item-notes-{{ $item->id }}" class="sr-only">{{ __('bcm::exercises.fields.notes') }}</label>
                                    <input id="item-notes-{{ $item->id }}" type="text" maxlength="2000" wire:model="itemResults.{{ $item->id }}.notes" placeholder="{{ __('bcm::exercises.fields.notes') }}" class="block w-full">
                                </div>
                            @else
                                <div class="lg:col-span-8">
                                    <p @class(['font-medium', 'text-green-700' => $item->recovered === true, 'text-red-700' => $item->recovered === false])>
                                        {{ __('bcm::exercises.recovered.'.match ($item->recovered) { true => 'yes', false => 'no', null => 'untested' }) }}@if ($item->measured_minutes !== null) · {{ $duration($item->measured_minutes) }}@endif
                                    </p>
                                    @if ($item->notes) <p class="text-xs text-gray-600">{{ $item->notes }}</p> @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($recording)
            <div class="print:hidden">
                <button type="button" wire:click="saveResults" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::exercises.ui.save_results') }}</button>
                <span wire:loading wire:target="saveResults" class="text-xs text-gray-500">{{ __('bcm::exercises.ui.saving') }}</span>
            </div>
        @endif

        <section aria-labelledby="findings-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="findings-heading" class="text-sm font-semibold">{{ __('bcm::exercises.ui.findings') }}</h2>
            @error('findings') <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($findings as $finding)
                    <li wire:key="finding-{{ $finding->id }}" class="flex flex-wrap items-start justify-between gap-2 py-2">
                        <div>
                            <p class="whitespace-pre-line">{{ $finding->description }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $finding->severity->label() }}
                                @if ($finding->step) · {{ __('bcm::exercises.ui.at_step', ['position' => $finding->step->position]) }} @endif
                                @if ($finding->author) · {{ $finding->author->name }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($finding->ticket_id)
                                @php($ticket = $tickets[$finding->ticket_id] ?? null)
                                @if ($ticket)
                                    <a href="{{ $ticket->url }}" class="text-blue-700 underline">{{ $ticket->number }}</a> <span class="text-xs text-gray-500">{{ $ticket->statusLabel }}</span>
                                @else
                                    <span>{{ $finding->ticket_number }}</span>
                                @endif
                            @else
                                @if ($canRaise)
                                    <button type="button" wire:click="raiseTicket('{{ $finding->id }}')" class="rounded-md bg-white px-2 py-1 text-xs font-semibold ring-1 ring-gray-300 print:hidden">{{ __('bcm::exercises.ui.raise_ticket') }}</button>
                                @endif
                                @if ($recording)
                                    <button type="button" wire:click="removeFinding('{{ $finding->id }}')" class="text-xs text-red-700 underline print:hidden">{{ __('bcm::exercises.ui.remove') }}</button>
                                @endif
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('bcm::exercises.ui.no_findings') }}</li>
                @endforelse
            </ul>

            @if ($recording)
                <form wire:submit="addFinding" class="grid gap-2 border-t border-gray-100 pt-3 sm:grid-cols-6 print:hidden" novalidate>
                    <div class="sm:col-span-6">
                        <label for="findingDescription" class="block text-sm font-medium">{{ __('bcm::exercises.fields.finding') }}</label>
                        <textarea id="findingDescription" wire:model="findingDescription" rows="2" maxlength="5000" class="mt-1 block w-full"></textarea>
                        @error('findingDescription') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="findingSeverity" class="block text-sm font-medium">{{ __('bcm::exercises.fields.severity') }}</label>
                        <select id="findingSeverity" wire:model="findingSeverity" class="mt-1 block w-full">
                            @foreach ($severities as $severity) <option value="{{ $severity->value }}" wire:key="severity-{{ $severity->value }}">{{ $severity->label() }}</option> @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label for="findingStepId" class="block text-sm font-medium">{{ __('bcm::exercises.fields.step') }}</label>
                        <select id="findingStepId" wire:model="findingStepId" class="mt-1 block w-full">
                            <option value="">—</option>
                            @foreach ($steps as $step) <option value="{{ $step->id }}" wire:key="finding-step-{{ $step->id }}">{{ $step->position }}. {{ \Illuminate\Support\Str::limit($step->action, 60) }}</option> @endforeach
                        </select>
                    </div>
                    <div class="flex items-end sm:col-span-1">
                        <button type="submit" class="rounded-md bg-white px-3 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('bcm::exercises.ui.add') }}</button>
                    </div>
                </form>
            @endif
        </section>

        @if ($recording)
            <form wire:submit="complete" wire:confirm="{{ __('bcm::exercises.ui.complete_confirm') }}" class="space-y-4 rounded-lg bg-white p-4 ring-1 ring-gray-200 print:hidden" novalidate>
                <h2 class="text-sm font-semibold">{{ __('bcm::exercises.ui.complete_heading') }}</h2>
                <fieldset class="space-y-1">
                    <legend class="text-sm font-medium">{{ __('bcm::exercises.fields.outcome') }} <span class="text-red-600" aria-hidden="true">*</span></legend>
                    @foreach ($outcomes as $option)
                        <label wire:key="outcome-{{ $option->value }}" class="flex items-center gap-2 text-sm"><input type="radio" wire:model="outcome" value="{{ $option->value }}"> {{ $option->label() }}</label>
                    @endforeach
                    @error('outcome') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>
                <div>
                    <label for="summary" class="block text-sm font-medium">{{ __('bcm::exercises.fields.summary') }}</label>
                    <textarea id="summary" wire:model="summary" rows="3" maxlength="10000" class="mt-1 block w-full"></textarea>
                </div>
                <div>
                    <label for="lessons" class="block text-sm font-medium">{{ __('bcm::exercises.fields.lessons') }}</label>
                    <textarea id="lessons" wire:model="lessons" rows="3" maxlength="10000" class="mt-1 block w-full"></textarea>
                </div>
                <p class="text-xs text-gray-500">{{ __('bcm::exercises.ui.complete_help') }}</p>
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::exercises.ui.complete') }}</button>
            </form>
        @elseif ($exercise->status === \Modules\Bcm\Domain\Enums\ExerciseStatus::Completed)
            <section aria-labelledby="conclusion-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200 text-sm">
                <h2 id="conclusion-heading" class="text-sm font-semibold">{{ __('bcm::exercises.ui.conclusion') }}</h2>
                <p><span class="text-xs text-gray-500">{{ __('bcm::exercises.fields.summary') }}:</span> <span class="whitespace-pre-line">{{ $exercise->summary ?? '—' }}</span></p>
                <p><span class="text-xs text-gray-500">{{ __('bcm::exercises.fields.lessons') }}:</span> <span class="whitespace-pre-line">{{ $exercise->lessons ?? '—' }}</span></p>
            </section>
        @endif
    @endif
</div>
