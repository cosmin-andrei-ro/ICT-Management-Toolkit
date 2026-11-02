@php($timezone = config()->string('app.display_timezone'))
<main class="mx-auto max-w-4xl space-y-8 bg-white p-8 text-sm text-gray-900 print:max-w-none print:p-0">
    <header class="space-y-1 border-b border-gray-300 pb-4">
        <p class="text-gray-600">{{ $organization?->name }}</p>
        <h1 class="text-2xl font-semibold">{{ __('risk::report.title') }}</h1>
        <p class="text-gray-600">{{ __('risk::report.as_of', ['date' => now()->setTimezone($timezone)->format('Y-m-d H:i')]) }}</p>
    </header>

    <section class="space-y-3">
        <h2 class="text-base font-semibold">{{ __('risk::report.overview') }}</h2>
        <div class="grid gap-6 sm:grid-cols-2">
            <x-risk::heat-map :heat="$heat['inherent']" :settings="$settings" :caption="__('risk::report.before')" />
            <x-risk::heat-map :heat="$heat['current']" :settings="$settings" :caption="__('risk::report.after')" />
        </div>
        <table class="min-w-full border border-gray-300 text-left">
            <thead class="bg-gray-100 text-xs"><tr>
                <th class="border border-gray-300 px-2 py-1">{{ __('risk::risks.fields.level') }}</th>
                <th class="border border-gray-300 px-2 py-1">{{ __('risk::report.before') }}</th>
                <th class="border border-gray-300 px-2 py-1">{{ __('risk::report.after') }}</th>
            </tr></thead>
            <tbody>
                @foreach (array_reverse($levels) as $level)
                    <tr><td class="border border-gray-300 px-2 py-1">{{ $level->label() }}</td><td class="border border-gray-300 px-2 py-1">{{ $byLevel['inherent'][$level->value] ?? 0 }}</td><td class="border border-gray-300 px-2 py-1">{{ $byLevel['current'][$level->value] ?? 0 }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @if ($unscored > 0) <p class="text-xs text-gray-600">{{ __('risk::report.unscored', ['count' => $unscored]) }}</p> @endif
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">{{ __('risk::report.method') }}</h2>
        <p class="text-xs text-gray-600">{{ __('risk::report.method_line', ['medium' => $settings->medium_from, 'high' => $settings->high_from, 'critical' => $settings->critical_from, 'approval' => $settings->approval_from, 'months' => $settings->review_months]) }}</p>
        <div class="grid gap-4 sm:grid-cols-2 text-xs">
            @foreach (['likelihood' => $settings->likelihood_levels, 'impact' => $settings->impact_levels] as $axis => $scale)
                <dl>
                    <dt class="font-medium">{{ __('risk::risks.fields.'.$axis) }}</dt>
                    @foreach ($scale as $index => $levelInfo) <dd>{{ $index + 1 }} {{ $levelInfo['label'] }} - {{ $levelInfo['description'] }}</dd> @endforeach
                </dl>
            @endforeach
        </div>
    </section>

    <section class="space-y-2 break-before-page">
        <h2 class="text-base font-semibold">{{ __('risk::report.register') }}</h2>
        <table class="min-w-full border border-gray-300 text-left text-xs">
            <thead class="bg-gray-100"><tr>
                <th class="border border-gray-300 px-1.5 py-1">{{ __('risk::risks.fields.risk') }}</th>
                <th class="border border-gray-300 px-1.5 py-1">{{ __('risk::risks.fields.owner') }}</th>
                <th class="border border-gray-300 px-1.5 py-1">{{ __('risk::risks.fields.inherent') }}</th>
                <th class="border border-gray-300 px-1.5 py-1">{{ __('risk::treatment.fields.option') }}</th>
                <th class="border border-gray-300 px-1.5 py-1">{{ __('risk::treatment.fields.residual') }}</th>
                <th class="border border-gray-300 px-1.5 py-1">{{ __('risk::report.export.accepted_until') }}</th>
            </tr></thead>
            <tbody>
                @forelse ($risks as $risk)
                    @php($inherent = $risk->inherentScore())
                    @php($residual = $risk->residualScore())
                    <tr>
                        <td class="border border-gray-300 px-1.5 py-1">{{ $risk->code() }} · {{ $risk->title }}</td>
                        <td class="border border-gray-300 px-1.5 py-1">{{ $risk->owner?->name ?? '—' }}</td>
                        <td class="border border-gray-300 px-1.5 py-1">{{ $inherent !== null ? $settings->level($inherent)->label().' · '.$inherent : '—' }}</td>
                        <td class="border border-gray-300 px-1.5 py-1">{{ $risk->treatment?->label() ?? '—' }}</td>
                        <td class="border border-gray-300 px-1.5 py-1">{{ $residual !== null ? $settings->level($residual)->label().' · '.$residual : '—' }}</td>
                        <td class="border border-gray-300 px-1.5 py-1">{{ $risk->isAccepted() ? $risk->accepted_until?->toDateString() : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="border border-gray-300 px-1.5 py-1 text-gray-500">{{ __('risk::risks.ui.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @foreach ($risks as $risk)
        <section class="space-y-1 break-inside-avoid-page border-t border-gray-200 pt-3">
            <h3 class="font-semibold">{{ $risk->code() }} · {{ $risk->title }}</h3>
            <p class="text-xs text-gray-600">{{ $risk->category->label() }} · {{ __('risk::risks.fields.owner') }}: {{ $risk->owner?->name ?? '—' }} · {{ __('risk::risks.fields.next_review') }}: {{ $risk->next_review_on?->toDateString() ?? '—' }}</p>
            @if ($risk->description) <p class="whitespace-pre-line">{{ $risk->description }}</p> @endif
            @if (($assets[$risk->id] ?? []) !== []) <p class="text-xs"><span class="text-gray-600">{{ __('risk::risks.ui.concerns') }}:</span> {{ implode('; ', array_map(fn ($asset) => $asset->type->label().': '.$asset->name, $assets[$risk->id])) }}</p> @endif
            @if ($risk->catalogEntries->isNotEmpty()) <p class="text-xs"><span class="text-gray-600">{{ __('risk::report.export.threats_vulnerabilities') }}:</span> {{ $risk->catalogEntries->pluck('name')->implode(', ') }}</p> @endif
            @if ($risk->existing_controls) <p class="text-xs"><span class="text-gray-600">{{ __('risk::risks.fields.existing_controls') }}:</span> {{ $risk->existing_controls }}</p> @endif
            @if ($risk->treatment_plan) <p class="text-xs"><span class="text-gray-600">{{ __('risk::treatment.fields.plan') }}:</span> {{ $risk->treatment_plan }}</p> @endif
            @if ($risk->actions->isNotEmpty())
                <ul class="list-disc pl-5 text-xs">
                    @foreach ($risk->actions as $action) <li>{{ $action->title }} - {{ $action->owner?->name ?? '—' }}, {{ $action->due_on?->toDateString() ?? '—' }}, {{ $action->status->label() }}@if ($action->isOverdue()) ({{ __('risk::report.export.overdue') }})@endif</li> @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</main>
