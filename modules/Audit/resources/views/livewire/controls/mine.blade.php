<div class="space-y-6">
    <header>
        <h1 class="text-xl font-semibold">{{ __('audit::compliance.mine.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('audit::compliance.mine.intro') }}</p>
    </header>
    <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
        @forelse ($controls as $control)
            <li wire:key="control-{{ $control->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                <a href="{{ route('audit.controls.show', $control) }}" class="text-blue-700 underline">{{ $control->code() }} · {{ $control->name }}</a>
                <span class="flex items-center gap-2">
                    @if ($control->next_test_on) <span @class(['text-xs', 'font-semibold text-red-700' => $control->isTestOverdue($today), 'text-gray-500' => ! $control->isTestOverdue($today)])>{{ __('audit::evidence.tests.next', ['date' => $control->next_test_on->toDateString()]) }}</span> @endif
                    <x-audit::status :status="$control->status" />
                </span>
            </li>
        @empty
            <li class="py-2 text-gray-500">{{ __('audit::compliance.mine.none') }}</li>
        @endforelse
    </ul>

    <section aria-labelledby="findings-heading" class="space-y-2">
        <h2 id="findings-heading" class="text-sm font-semibold">{{ __('audit::audits.findings.mine') }}</h2>
        <ul class="divide-y divide-gray-100 rounded-lg bg-white px-4 text-sm ring-1 ring-gray-200">
            @forelse ($findings as $finding)
                <li wire:key="finding-{{ $finding->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <a href="{{ route('audit.findings.show', $finding) }}" class="text-blue-700 underline">{{ $finding->code() }} · {{ $finding->title }}</a>
                    <span class="flex items-center gap-2 text-xs">
                        @if ($finding->due_on) <span @class(['font-semibold text-red-700' => $finding->isOverdue($today), 'text-gray-500' => ! $finding->isOverdue($today)])>{{ __('audit::audits.findings.due', ['date' => $finding->due_on->toDateString()]) }}</span> @endif
                        <x-audit::severity :severity="$finding->severity" />
                    </span>
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('audit::audits.findings.none_mine') }}</li>
            @endforelse
        </ul>
    </section>
</div>
