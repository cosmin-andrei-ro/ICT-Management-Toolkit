@php($format = fn (\Modules\Core\Domain\Metrics\Metric $metric, int|float|null $value): string => match (true) {
    $value === null => '—',
    $metric->unit === \Modules\Core\Domain\Metrics\MetricUnit::Percent => \Illuminate\Support\Number::format($value, maxPrecision: 1).' %',
    $metric->unit === \Modules\Core\Domain\Metrics\MetricUnit::Minutes => \Modules\Core\Domain\Time\Duration::format((int) $value),
    default => \Illuminate\Support\Number::format($value),
})
<section aria-labelledby="dashboard-heading" class="space-y-4">
    @if ($available)
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="dashboard-heading" class="text-lg font-semibold">{{ __('reporting::dashboard.title') }}</h2>
            <div class="flex gap-2">
                @if ($customising)
                    <button type="button" wire:click="restoreDefaults" class="rounded-md bg-white px-3 py-1.5 text-sm ring-1 ring-gray-300">{{ __('reporting::dashboard.restore') }}</button>
                @endif
                <button type="button" wire:click="toggleCustomising" aria-pressed="{{ $customising ? 'true' : 'false' }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">
                    {{ $customising ? __('reporting::dashboard.done') : __('reporting::dashboard.customise') }}
                </button>
            </div>
        </div>

        @if ($customising)
            <p class="text-sm text-gray-600">{{ __('reporting::dashboard.customise_help') }}</p>
        @endif

        @forelse ($groups as $group)
            <div wire:key="group-{{ $group['source']->key }}" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold">{{ __($group['source']->label) }}</h3>
                    @if ($customising)
                        <div class="flex gap-1 text-xs">
                            <button type="button" wire:click="moveUp('{{ $group['source']->key }}')" @disabled($loop->first) class="rounded px-2 py-1 ring-1 ring-gray-300 disabled:opacity-40">{{ __('reporting::dashboard.move_up') }}<span class="sr-only">: {{ __($group['source']->label) }}</span></button>
                            <button type="button" wire:click="moveDown('{{ $group['source']->key }}')" @disabled($loop->last) class="rounded px-2 py-1 ring-1 ring-gray-300 disabled:opacity-40">{{ __('reporting::dashboard.move_down') }}<span class="sr-only">: {{ __($group['source']->label) }}</span></button>
                        </div>
                    @endif
                </div>
                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($group['metrics'] as $row)
                        @php($metric = $row['metric'])
                        @php($isHidden = in_array($metric->key, $hidden, true))
                        <div wire:key="metric-{{ $metric->key }}" @class([
                            'rounded-lg p-3 ring-1',
                            'bg-green-50 ring-green-200' => $row['status'] === \Modules\Core\Domain\Metrics\MetricStatus::Good,
                            'bg-amber-50 ring-amber-300' => $row['status'] === \Modules\Core\Domain\Metrics\MetricStatus::Warning,
                            'bg-red-50 ring-red-300' => $row['status'] === \Modules\Core\Domain\Metrics\MetricStatus::Alert,
                            'bg-white ring-gray-200' => $row['status'] === \Modules\Core\Domain\Metrics\MetricStatus::Neutral,
                            'opacity-50' => $isHidden,
                        ])>
                            <dt class="text-xs text-gray-600">{{ __($metric->label) }}</dt>
                            <dd class="flex items-baseline justify-between gap-2">
                                @if ($metric->url() !== null && ! $customising)
                                    <a href="{{ $metric->url() }}" class="text-2xl font-semibold text-gray-900 underline decoration-gray-300 hover:decoration-gray-900">{{ $format($metric, $row['value']) }}</a>
                                @else
                                    <span class="text-2xl font-semibold">{{ $format($metric, $row['value']) }}</span>
                                @endif
                                @if ($row['status'] !== \Modules\Core\Domain\Metrics\MetricStatus::Neutral)
                                    <span class="text-xs font-medium">{{ __('reporting::dashboard.status.'.$row['status']->value) }}</span>
                                @endif
                            </dd>
                            @if ($customising)
                                <button type="button" wire:click="{{ $isHidden ? 'show' : 'hide' }}('{{ $metric->key }}')" class="mt-2 text-xs font-medium text-blue-700 underline">
                                    {{ $isHidden ? __('reporting::dashboard.show') : __('reporting::dashboard.hide') }}<span class="sr-only">: {{ __($metric->label) }}</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </dl>
            </div>
        @empty
            <p class="text-sm text-gray-600">{{ __('reporting::dashboard.all_hidden') }}</p>
        @endforelse
    @endif
</section>
