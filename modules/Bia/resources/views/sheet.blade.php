@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
<x-core::printable :title="__('bia::sheet.title').' · '.$process->name" :pdf="$pdf" :styles="$styles ?? ''" :back-url="route('bia.processes.show', $process)" :back-label="'← '.$process->name" :pdf-url="$pdfAvailable ? route('bia.processes.sheet.pdf', $process) : null">
    <main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ $organization?->name }}</p>
            <h1 class="text-xl font-semibold">{{ __('bia::sheet.title') }}: {{ $process->name }}</h1>
            <p class="text-gray-600">
                @if ($assessment)
                    {{ __('bia::sheet.version', ['version' => $assessment->version, 'status' => $assessment->status->label()]) }}
                    @if ($assessment->decider && $assessment->decided_at) · {{ __('bia::sheet.approved', ['name' => $assessment->decider->name, 'date' => $assessment->decided_at->timezone($timezone)->format('Y-m-d')]) }} @endif
                @else
                    {{ __('bia::processes.ui.not_assessed') }}
                @endif
                · {{ __('bia::sheet.printed', ['date' => now()->timezone($timezone)->format('Y-m-d H:i')]) }}
            </p>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 print:grid-cols-2">
            <dl class="space-y-1">
                <div><dt class="inline text-gray-500">{{ __('bia::processes.fields.department') }}:</dt> <dd class="inline">{{ $process->department?->name ?? '—' }}</dd></div>
                <div><dt class="inline text-gray-500">{{ __('bia::processes.fields.owner') }}:</dt> <dd class="inline">{{ $process->owner?->name ?? '—' }}</dd></div>
                <div><dt class="inline text-gray-500">{{ __('bia::processes.fields.deputy') }}:</dt> <dd class="inline">{{ $process->deputy?->name ?? '—' }}</dd></div>
                <div><dt class="inline text-gray-500">{{ __('bia::processes.fields.next_review') }}:</dt> <dd class="inline">{{ $process->next_review_on?->toDateString() ?? '—' }}</dd></div>
            </dl>
            @if ($assessment)
                <dl class="grid grid-cols-4 gap-2 rounded border border-gray-300 p-3 text-center">
                    <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.tier') }}</dt><dd class="font-semibold">{{ $assessment->rto_minutes !== null ? \Modules\Bia\Domain\Enums\Tier::fromRto($assessment->rto_minutes)->value : '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.mtpd') }}</dt><dd class="font-semibold">{{ $duration($assessment->mtpd_minutes) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.rto') }}</dt><dd class="font-semibold">{{ $duration($assessment->rto_minutes) }}</dd></div>
                    <div><dt class="text-xs text-gray-500">{{ __('bia::processes.fields.rpo') }}</dt><dd class="font-semibold">{{ $duration($assessment->rpo_minutes) }}</dd></div>
                </dl>
            @endif
        </section>

        @if ($process->description || $process->peak_periods)
            <section class="space-y-1">
                @if ($process->description) <p class="whitespace-pre-line">{{ $process->description }}</p> @endif
                @if ($process->peak_periods) <p><span class="text-gray-500">{{ __('bia::processes.fields.peak_periods') }}:</span> {{ $process->peak_periods }}</p> @endif
            </section>
        @endif

        @if ($assessment)
            <section class="space-y-2 break-inside-avoid">
                <h2 class="font-semibold">{{ __('bia::assessments.ui.matrix') }}</h2>
                <p class="text-xs text-gray-500">{{ __('bia::assessments.ui.unacceptable', ['level' => $assessment->unacceptable_level ?? \Modules\Bia\Domain\Models\BiaSettings::current()->unacceptable_level]) }}</p>
                <table class="w-full border border-gray-300">
                    <thead class="bg-gray-50 text-xs">
                        <tr>
                            <th class="border border-gray-300 px-2 py-1 text-left">{{ __('bia::assessments.ui.category') }}</th>
                            @foreach ($horizons as $horizon) <th class="border border-gray-300 px-2 py-1">{{ $horizon->label }}</th> @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <th class="border border-gray-300 px-2 py-1 text-left font-medium">{{ $category->name }}</th>
                                @foreach ($horizons as $horizon)
                                    @php($value = $scores[$category->id.'|'.$horizon->id] ?? null)
                                    <td @class(['border border-gray-300 px-2 py-1 text-center', 'font-bold' => $value !== null && $value >= ($assessment->unacceptable_level ?? 4)])>{{ $value ?? '–' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($assessment->mtpd_reason) <p><span class="text-gray-500">{{ __('bia::sheet.mtpd_reason') }}:</span> {{ $assessment->mtpd_reason }}</p> @endif
            </section>

            <section class="space-y-2 break-inside-avoid">
                <h2 class="font-semibold">{{ __('bia::sheet.continuity') }}</h2>
                <dl class="space-y-1">
                    <div><dt class="text-gray-500">{{ __('bia::assessments.ui.mbco') }}</dt><dd class="whitespace-pre-line">{{ $assessment->mbco ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('bia::assessments.ui.workarounds') }}</dt><dd class="whitespace-pre-line">{{ $assessment->workarounds ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('bia::assessments.ui.minimum_staff') }}</dt><dd>{{ $assessment->minimum_staff ?? '—' }}</dd></div>
                    @if ($assessment->notes) <div><dt class="text-gray-500">{{ __('bia::assessments.ui.notes') }}</dt><dd class="whitespace-pre-line">{{ $assessment->notes }}</dd></div> @endif
                </dl>
            </section>

            <section class="space-y-2 break-inside-avoid">
                <h2 class="font-semibold">{{ __('bia::dependencies.title') }}</h2>
                <ul class="list-disc space-y-0.5 pl-5">
                    @foreach ($items as $item) <li>{{ __('bia::dependencies.systems') }}: {{ $item->name }} ({{ $item->typeName }})</li> @endforeach
                    @foreach ($dependencies as $dependency)
                        <li>{{ $dependency->kind->label() }}: {{ $dependency->label() }}@if ($dependency->minimum_people !== null), {{ trans_choice('bia::dependencies.minimum', $dependency->minimum_people, ['count' => $dependency->minimum_people]) }}@endif</li>
                    @endforeach
                    @if ($items === [] && $dependencies->isEmpty()) <li class="list-none text-gray-500">{{ __('bia::dependencies.none') }}</li> @endif
                </ul>
            </section>

            <section class="space-y-1 border-t border-gray-300 pt-3 text-gray-600">
                @if ($assessment->submitter && $assessment->submitted_at) <p>{{ __('bia::assessments.ui.submitted_by', ['name' => $assessment->submitter->name, 'date' => $assessment->submitted_at->timezone($timezone)->format('Y-m-d')]) }}</p> @endif
                @if ($assessment->decision_comment) <p>{{ __('bia::assessments.ui.comment') }}: {{ $assessment->decision_comment }}</p> @endif
            </section>
        @endif
    </main>
</x-core::printable>
