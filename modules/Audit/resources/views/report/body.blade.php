@php($timezone = config()->string('app.display_timezone'))
@php($cell = 'border border-gray-300 px-1.5 py-1')
<main class="mx-auto max-w-4xl space-y-8 bg-white p-8 text-sm text-gray-900 print:max-w-none print:p-0">
    <header class="space-y-1 border-b border-gray-300 pb-4">
        <p class="text-gray-600">{{ $organization?->name }}</p>
        <h1 class="text-2xl font-semibold">{{ __('audit::report.title') }}</h1>
        <p class="text-gray-600">{{ __('audit::report.as_of', ['date' => now()->setTimezone($timezone)->format('Y-m-d H:i')]) }}</p>
    </header>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">{{ __('audit::report.frameworks') }}</h2>
        <table class="min-w-full border border-gray-300 text-left text-xs">
            <thead class="bg-gray-100"><tr>
                <th class="{{ $cell }}">{{ __('audit::compliance.fields.framework') }}</th>
                <th class="{{ $cell }}">{{ __('audit::report.requirements') }}</th>
                <th class="{{ $cell }}">{{ __('audit::compliance.requirement_states.not_applicable') }}</th>
                <th class="{{ $cell }}">{{ __('audit::compliance.requirement_states.undecided') }}</th>
                <th class="{{ $cell }}">{{ __('audit::compliance.requirement_states.not_covered') }}</th>
                <th class="{{ $cell }}">{{ __('audit::compliance.requirement_states.implemented') }}</th>
                <th class="{{ $cell }}">{{ __('audit::report.met_share') }}</th>
            </tr></thead>
            <tbody>
                @forelse ($frameworks as $framework)
                    @php($figures = $coverage[$framework->id])
                    <tr>
                        <td class="{{ $cell }}">{{ $framework->name }}</td>
                        <td class="{{ $cell }}">{{ $figures['total'] }}</td>
                        <td class="{{ $cell }}">{{ $figures['states']['not_applicable'] }}</td>
                        <td class="{{ $cell }}">{{ $figures['states']['undecided'] }}</td>
                        <td class="{{ $cell }}">{{ $figures['states']['not_covered'] }}</td>
                        <td class="{{ $cell }}">{{ $figures['states']['implemented'] }}</td>
                        <td class="{{ $cell }}">{{ $figures['implemented_percent'] !== null ? $figures['implemented_percent'].'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="{{ $cell }}">{{ __('audit::compliance.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">{{ __('audit::compliance.controls.title') }}</h2>
        <p>
            @foreach ($controlStatuses as $row) {{ $row['status']->label() }}: {{ $row['count'] }}@if (! $loop->last) · @endif @endforeach
        </p>
        <ul class="list-disc pl-5 text-xs">
            <li>{{ trans_choice('audit::report.tests_overdue', $testsOverdue->count(), ['count' => $testsOverdue->count()]) }}@if ($testsOverdue->isNotEmpty()): {{ $testsOverdue->map(fn ($control) => $control->code())->join(', ') }}@endif</li>
            <li>{{ trans_choice('audit::report.unmapped', $unmapped->count(), ['count' => $unmapped->count()]) }}@if ($unmapped->isNotEmpty()): {{ $unmapped->map(fn ($control) => $control->code())->join(', ') }}@endif</li>
            <li>{{ trans_choice('audit::report.expired_evidence', $expiredEvidence, ['count' => $expiredEvidence]) }}</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">{{ __('audit::report.audits') }}</h2>
        <table class="min-w-full border border-gray-300 text-left text-xs">
            <thead class="bg-gray-100"><tr>
                <th class="{{ $cell }}">{{ __('audit::audits.fields.audit') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.fields.kind') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.fields.starts_on') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.fields.status') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.findings.title') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.fields.conclusion') }}</th>
            </tr></thead>
            <tbody>
                @forelse ($audits as $audit)
                    <tr class="align-top">
                        <td class="{{ $cell }}">{{ $audit->code() }} · {{ $audit->title }}</td>
                        <td class="{{ $cell }}">{{ $audit->kind->label() }}</td>
                        <td class="{{ $cell }}">{{ $audit->starts_on->toDateString() }}</td>
                        <td class="{{ $cell }}">{{ $audit->status->label() }}</td>
                        <td class="{{ $cell }}">{{ $audit->findings_count }}</td>
                        <td class="{{ $cell }} whitespace-pre-line">{{ $audit->conclusion }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="{{ $cell }}">{{ __('audit::audits.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="space-y-2 break-before-page">
        <h2 class="text-base font-semibold">{{ __('audit::report.open_findings') }}</h2>
        <p>@foreach ($findingsBySeverity as $row) {{ $row['severity']->label() }}: {{ $row['count'] }}@if (! $loop->last) · @endif @endforeach</p>
        <table class="min-w-full border border-gray-300 text-left text-xs">
            <thead class="bg-gray-100"><tr>
                <th class="{{ $cell }}">{{ __('audit::audits.findings.fields.finding') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.findings.fields.severity') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.findings.fields.owner') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.findings.fields.due_on') }}</th>
                <th class="{{ $cell }}">{{ __('audit::audits.findings.fields.status') }}</th>
            </tr></thead>
            <tbody>
                @forelse ($openFindings as $finding)
                    <tr class="break-inside-avoid align-top">
                        <td class="{{ $cell }}">{{ $finding->code() }} · {{ $finding->title }}@if ($finding->audit) <span class="block text-gray-600">{{ $finding->audit->code() }}</span>@endif</td>
                        <td class="{{ $cell }}">{{ $finding->severity->label() }}</td>
                        <td class="{{ $cell }}">{{ $finding->owner?->name ?? '—' }}</td>
                        <td @class([$cell, 'font-semibold text-red-700' => $finding->isOverdue($today)])>{{ $finding->due_on?->toDateString() ?? '—' }}</td>
                        <td class="{{ $cell }}">{{ $finding->status->label() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="{{ $cell }}">{{ __('audit::audits.findings.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</main>
