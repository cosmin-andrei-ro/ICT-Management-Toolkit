<div class="space-y-6" @if ($preparing) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('ticketing::reports.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('ticketing::reports.intro', ['timezone' => $timezone]) }}</p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <label class="text-sm">
                <span class="block text-gray-600">{{ __('ticketing::reports.from') }}</span>
                <input type="date" wire:model.live="from" class="rounded-md border-gray-300 text-sm">
            </label>
            <label class="text-sm">
                <span class="block text-gray-600">{{ __('ticketing::reports.until') }}</span>
                <input type="date" wire:model.live="until" class="rounded-md border-gray-300 text-sm">
            </label>
        </div>
    </div>
    @error('from') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($summary !== null)
        <section aria-labelledby="kpi-heading" class="space-y-2">
            <h2 id="kpi-heading" class="sr-only">{{ __('ticketing::reports.indicators') }}</h2>
            <dl class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    'created' => $summary['created'],
                    'resolved' => $summary['resolved'],
                    'average_hours' => $summary['average_hours'],
                    'median_hours' => $summary['median_hours'],
                    'reopen_rate' => $summary['reopen_rate'] !== null ? $summary['reopen_rate'].' %' : null,
                ] as $key => $value)
                    <div wire:key="kpi-{{ $key }}" class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
                        <dt class="text-xs text-gray-500">{{ __('ticketing::reports.kpi.'.$key) }}</dt>
                        <dd class="text-xl font-semibold">{{ $value ?? '—' }}</dd>
                    </div>
                @endforeach
                @foreach ($sla as $row)
                    <div wire:key="sla-{{ $row['metric']->value }}" class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
                        <dt class="text-xs text-gray-500">{{ __('ticketing::reports.kpi.sla_compliance', ['metric' => $row['metric']->label()]) }}</dt>
                        <dd class="text-xl font-semibold">{{ $row['compliance'] !== null ? $row['compliance'].' %' : '—' }}</dd>
                        <dd class="text-xs text-gray-500">{{ __('ticketing::reports.sla_counts', ['met' => $row['met'], 'breached' => $row['breached']]) }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="text-xs text-gray-500">{{ __('ticketing::reports.kpi_help') }}</p>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="backlog-heading" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="backlog-heading" class="mb-2 text-sm font-semibold">{{ __('ticketing::reports.backlog') }}</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-gray-500">
                    <tr><th class="py-1">{{ __('ticketing::reports.export.queue') }}</th><th class="py-1 text-right">{{ __('ticketing::reports.open') }}</th><th class="py-1 text-right">{{ __('ticketing::reports.oldest') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($backlog as $row)
                        <tr wire:key="backlog-{{ $loop->index }}" class="border-t border-gray-100">
                            <td class="py-1">{{ $row['queue'] ?? __('ticketing::reports.no_queue') }}</td>
                            <td class="py-1 text-right">{{ $row['open'] }}</td>
                            <td class="py-1 text-right text-gray-600">{{ $row['oldest']?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-gray-500">{{ __('ticketing::reports.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section aria-labelledby="category-heading" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="category-heading" class="mb-2 text-sm font-semibold">{{ __('ticketing::reports.by_category') }}</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-gray-500">
                    <tr><th class="py-1">{{ __('ticketing::reports.export.category') }}</th><th class="py-1 text-right">{{ __('ticketing::reports.kpi.created') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($categories as $row)
                        <tr wire:key="category-{{ $loop->index }}" class="border-t border-gray-100">
                            <td class="py-1">{{ $row['category'] ?? __('ticketing::reports.no_category') }}</td>
                            <td class="py-1 text-right">{{ $row['created'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-2 text-gray-500">{{ __('ticketing::reports.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <section aria-labelledby="export-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="export-heading" class="text-sm font-semibold">{{ __('ticketing::reports.export_title') }}</h2>
        <p class="text-sm text-gray-600">{{ __('ticketing::reports.export_help') }}</p>
        @if ($period !== null)
            <div class="flex gap-2">
                <button type="button" wire:click="export('xlsx')" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::reports.export_xlsx') }}</button>
                <button type="button" wire:click="export('csv')" wire:loading.attr="disabled" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('ticketing::reports.export_csv') }}</button>
            </div>
        @endif
        @if ($exports->isNotEmpty())
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($exports as $export)
                    <li wire:key="export-{{ $export->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span>{{ strtoupper($export->format->value) }} · {{ $export->parameters['from'] ?? '' }} – {{ $export->parameters['until'] ?? '' }}</span>
                        @if ($export->isDownloadable())
                            <span class="text-gray-500">
                                <a href="{{ route('core.exports.download', $export) }}" class="font-medium text-blue-700 underline">{{ __('core::exports.download') }}</a>
                                · {{ __('core::exports.expires', ['date' => $export->expires_at?->timezone($timezone)->format('Y-m-d H:i')]) }}
                            </span>
                        @else
                            <span class="text-gray-500">{{ $export->status->label() }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
