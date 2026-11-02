@php($title = __('reporting::management.report.title', ['month' => $month->isoFormat('MMMM YYYY')]))
<x-core::printable :title="$title" :pdf="$pdf" :styles="$styles ?? ''" :back-url="route('reporting.management')" :back-label="'← '.__('reporting::management.title')" :pdf-url="$pdfAvailable ? route('reporting.report.pdf', ['month' => $month->format('Y-m')]) : null">
    <main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ $organization?->name }}</p>
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            <p class="text-gray-600">{{ __('reporting::management.report.as_of', ['date' => $end->isoFormat('D MMMM YYYY')]) }} · {{ __('reporting::management.report.printed', ['date' => now()->timezone(config()->string('app.display_timezone'))->format('Y-m-d H:i')]) }}</p>
        </header>

        <section aria-labelledby="summary-heading" class="space-y-2">
            <h2 id="summary-heading" class="text-base font-semibold">{{ __('reporting::management.report.summary') }}</h2>
            <dl class="grid grid-cols-3 gap-3">
                @foreach (['alert', 'warning', 'good'] as $status)
                    <div class="rounded border border-gray-300 p-3">
                        <dt class="text-xs text-gray-600">{{ __('reporting::dashboard.status.'.$status) }}</dt>
                        <dd class="text-2xl font-semibold">{{ $statuses[$status] }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="text-xs text-gray-600">{{ __('reporting::management.report.summary_help') }}</p>
        </section>

        @foreach ($groups as $group)
            <section class="space-y-2 break-inside-avoid">
                <h2 class="text-base font-semibold">{{ __($group['label']) }}</h2>
                <table class="min-w-full text-left">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-1 pr-3 font-medium">{{ __('reporting::management.columns.indicator') }}</th>
                            <th scope="col" class="py-1 pr-3 text-right font-medium">{{ __('reporting::management.report.end_of_month') }}</th>
                            <th scope="col" class="py-1 pr-3 text-right font-medium">{{ __('reporting::management.report.month_before') }}</th>
                            <th scope="col" class="py-1 pr-3 font-medium">{{ __('reporting::management.columns.status') }}</th>
                            <th scope="col" class="w-36 py-1 font-medium">{{ __('reporting::management.report.twelve_months') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($group['rows'] as $row)
                            <tr>
                                <td class="py-1 pr-3">{{ __($row['metric']->label) }}</td>
                                <td class="py-1 pr-3 text-right font-semibold tabular-nums">{{ $row['metric']->format($row['closing']) }}</td>
                                <td class="py-1 pr-3 text-right tabular-nums text-gray-600">{{ $row['metric']->format($row['previous']) }}</td>
                                <td class="py-1 pr-3"><x-reporting::status :status="$row['status']" /></td>
                                <td class="py-1"><x-reporting::sparkline :series="$row['year']" :label="__('reporting::management.trend_of', ['metric' => __($row['metric']->label)])" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach

        <p class="text-xs text-gray-500">{{ __('reporting::management.report.footnote') }}</p>
    </main>
</x-core::printable>
