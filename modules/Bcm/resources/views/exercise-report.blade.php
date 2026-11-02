@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
@php($cell = 'border border-gray-300 px-1.5 py-1')
<x-core::printable :title="$exercise->title" :pdf="$pdf" :styles="$styles ?? ''" :back-url="route('bcm.exercises.show', $exercise)" :back-label="'← '.$exercise->title" :pdf-url="$pdfAvailable ? route('bcm.exercises.report.pdf', $exercise) : null">
    <main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ $organization?->name }} · {{ $plan->type->label() }} · {{ $plan->name }}@if ($exercise->version) · {{ __('bcm::plans.ui.version', ['version' => $exercise->version->version]) }}@endif</p>
            <h1 class="text-xl font-semibold">{{ __('bcm::exercises.report.title') }}: {{ $exercise->title }}</h1>
            <p class="text-gray-600">{{ __('bcm::sheet.printed', ['date' => now()->timezone($timezone)->format('Y-m-d H:i')]) }}</p>
        </header>

        <dl class="grid gap-2 sm:grid-cols-2 print:grid-cols-2">
            <div><dt class="inline text-gray-500">{{ __('bcm::exercises.fields.type') }}:</dt> <dd class="inline">{{ $exercise->type->label() }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('bcm::exercises.fields.scenario') }}:</dt> <dd class="inline">{{ $exercise->scenario ? __('bcm::plans.scenarios.'.$exercise->scenario->value) : '—' }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('bcm::exercises.fields.facilitator') }}:</dt> <dd class="inline">{{ $exercise->facilitator?->name ?? '—' }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('bcm::exercises.fields.outcome') }}:</dt> <dd class="inline font-semibold">{{ $exercise->outcome?->label() ?? '—' }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('bcm::exercises.fields.started_at') }}:</dt> <dd class="inline">{{ $exercise->started_at?->timezone($timezone)->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('bcm::exercises.fields.completed_at') }}:</dt> <dd class="inline">{{ $exercise->completed_at?->timezone($timezone)->format('Y-m-d H:i') ?? '—' }}</dd></div>
            @if ($exercise->participants) <div class="sm:col-span-2 print:col-span-2"><dt class="inline text-gray-500">{{ __('bcm::exercises.fields.participants') }}:</dt> <dd class="inline whitespace-pre-line">{{ $exercise->participants }}</dd></div> @endif
        </dl>

        <p class="rounded border border-gray-300 p-3">
            @if ($exercise->measured_minutes === null)
                {{ __('bcm::exercises.ui.no_measure') }}
            @else
                {{ __('bcm::exercises.ui.measured', ['value' => $duration($exercise->measured_minutes), 'estimate' => $duration($estimate)]) }}
                @if ($exercise->rto_minutes !== null)
                    <strong>{{ __($exercise->measured_minutes > $exercise->rto_minutes ? 'bcm::exercises.ui.over_rto' : 'bcm::exercises.ui.within_rto', ['value' => $duration($exercise->rto_minutes)]) }}</strong>
                @endif
            @endif
        </p>

        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ __('bcm::exercises.ui.steps') }}</h2>
            <table class="min-w-full border border-gray-300 text-left text-xs">
                <thead class="bg-gray-100"><tr>
                    <th class="{{ $cell }} w-8">#</th>
                    <th class="{{ $cell }}">{{ __('bcm::exercises.report.action') }}</th>
                    <th class="{{ $cell }}">{{ __('bcm::exercises.report.estimated') }}</th>
                    <th class="{{ $cell }}">{{ __('bcm::exercises.fields.result') }}</th>
                    <th class="{{ $cell }}">{{ __('bcm::exercises.fields.actual') }}</th>
                    <th class="{{ $cell }}">{{ __('bcm::exercises.fields.notes') }}</th>
                </tr></thead>
                <tbody>
                    @forelse ($steps as $step)
                        <tr class="break-inside-avoid align-top">
                            <td class="{{ $cell }}">{{ $step->position }}</td>
                            <td class="{{ $cell }}"><span class="uppercase text-gray-500">{{ $step->phase->label() }}</span> {{ $step->action }}@if ($step->configuration_item_id && isset($profiles[$step->configuration_item_id])) <span class="block text-gray-500">{{ $profiles[$step->configuration_item_id]->name }}</span>@endif</td>
                            <td class="{{ $cell }}">{{ $duration($step->estimated_minutes) }}</td>
                            <td class="{{ $cell }} font-medium">{{ $step->result->label() }}</td>
                            <td class="{{ $cell }}">{{ $step->actual_minutes !== null ? $duration($step->actual_minutes) : '—' }}</td>
                            <td class="{{ $cell }}">{{ $step->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="{{ $cell }}">{{ __('bcm::exercises.ui.no_steps') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @if ($exerciseItems->isNotEmpty())
            <section class="space-y-2">
                <h2 class="text-base font-semibold">{{ __('bcm::exercises.ui.items') }}</h2>
                <table class="min-w-full border border-gray-300 text-left text-xs">
                    <thead class="bg-gray-100"><tr>
                        <th class="{{ $cell }}">{{ __('bcm::exercises.report.system') }}</th>
                        <th class="{{ $cell }}">{{ __('bcm::exercises.report.declared') }}</th>
                        <th class="{{ $cell }}">{{ __('bcm::exercises.fields.recovered') }}</th>
                        <th class="{{ $cell }}">{{ __('bcm::exercises.fields.measured') }}</th>
                        <th class="{{ $cell }}">{{ __('bcm::exercises.fields.notes') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($exerciseItems as $item)
                            @php($profile = $profiles[$item->configuration_item_id] ?? null)
                            <tr class="break-inside-avoid align-top">
                                <td class="{{ $cell }}">{{ $profile?->name ?? __('bcm::exercises.ui.unknown_item') }}</td>
                                <td class="{{ $cell }}">{{ $profile ? $duration($profile->recoveryTimeMinutes) : '—' }}</td>
                                <td class="{{ $cell }} font-medium">{{ __('bcm::exercises.recovered.'.match ($item->recovered) { true => 'yes', false => 'no', null => 'untested' }) }}</td>
                                <td class="{{ $cell }}">{{ $item->measured_minutes !== null ? $duration($item->measured_minutes) : '—' }}</td>
                                <td class="{{ $cell }}">{{ $item->notes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ __('bcm::exercises.ui.findings') }}</h2>
            <ul class="list-disc space-y-1 pl-5">
                @forelse ($findings as $finding)
                    <li class="break-inside-avoid">
                        <span class="whitespace-pre-line">{{ $finding->description }}</span>
                        <span class="text-gray-600">({{ $finding->severity->label() }}@if ($finding->step), {{ __('bcm::exercises.ui.at_step', ['position' => $finding->step->position]) }}@endif@if ($finding->ticket_id), {{ ($tickets[$finding->ticket_id] ?? null)?->number ?? $finding->ticket_number }}@endif)</span>
                    </li>
                @empty
                    <li>{{ __('bcm::exercises.ui.no_findings') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="break-inside-avoid space-y-1">
            <h2 class="text-base font-semibold">{{ __('bcm::exercises.fields.summary') }}</h2>
            <p class="whitespace-pre-line">{{ $exercise->summary ?? '—' }}</p>
            <h2 class="pt-2 text-base font-semibold">{{ __('bcm::exercises.fields.lessons') }}</h2>
            <p class="whitespace-pre-line">{{ $exercise->lessons ?? '—' }}</p>
        </section>
    </main>
</x-core::printable>
