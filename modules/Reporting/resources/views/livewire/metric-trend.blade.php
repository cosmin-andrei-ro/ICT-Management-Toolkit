<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm"><a href="{{ route('home') }}" class="text-blue-700 underline">{{ __('reporting::dashboard.title') }}</a></p>
            <h1 class="text-xl font-semibold">{{ __($metric->label) }}</h1>
        </div>
        <div class="flex gap-1" role="group" aria-label="{{ __('reporting::trends.period') }}">
            @foreach (\Modules\Reporting\Application\Trends::PERIODS as $option)
                <button type="button" wire:key="period-{{ $option }}" wire:click="period({{ $option }})" aria-pressed="{{ $period === $option ? 'true' : 'false' }}" @class(['rounded-md px-3 py-1.5 text-sm ring-1', 'bg-gray-900 text-white ring-gray-900' => $period === $option, 'bg-white ring-gray-300' => $period !== $option])>
                    {{ trans_choice('reporting::trends.days', $option, ['count' => $option]) }}
                </button>
            @endforeach
        </div>
    </div>

    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
            <dt class="text-xs text-gray-600">{{ __('reporting::trends.now') }}</dt>
            <dd class="text-2xl font-semibold">{{ $metric->format($current) }}</dd>
            @if ($status !== \Modules\Core\Domain\Metrics\MetricStatus::Neutral)
                <dd class="text-xs font-medium">{{ __('reporting::dashboard.status.'.$status->value) }}</dd>
            @endif
        </div>
        @if ($summary !== null)
            @foreach (['first', 'lowest', 'highest', 'average'] as $figure)
                <div wire:key="summary-{{ $figure }}" class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
                    <dt class="text-xs text-gray-600">{{ __('reporting::trends.summary.'.$figure) }}</dt>
                    <dd class="text-2xl font-semibold">{{ $metric->format($summary[$figure]) }}</dd>
                </div>
            @endforeach
        @endif
    </dl>

    <section aria-labelledby="trend-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="trend-heading" class="text-sm font-semibold">{{ trans_choice('reporting::trends.over', $period, ['count' => $period]) }}</h2>
        @if ($summary === null)
            <p class="text-sm text-gray-600">{{ __('reporting::trends.no_values') }}</p>
        @else
            <x-reporting::trend-chart :series="$series" :metric="$metric" :label="__($metric->label).' — '.trans_choice('reporting::trends.over', $period, ['count' => $period])" />
            <details class="text-sm">
                <summary class="cursor-pointer text-blue-700 underline">{{ __('reporting::trends.table') }}</summary>
                <table class="mt-2 min-w-full text-left">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-1 pr-4 font-medium">{{ __('reporting::trends.date') }}</th>
                            <th scope="col" class="py-1 pr-4 font-medium">{{ __('reporting::trends.value') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach (array_reverse($series, true) as $date => $value)
                            @if ($value !== null)
                                <tr wire:key="row-{{ $date }}">
                                    <td class="py-1 pr-4">{{ \Carbon\CarbonImmutable::parse($date)->isoFormat('D MMM YYYY') }}</td>
                                    <td class="py-1 pr-4">{{ $metric->format($value) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </details>
        @endif
        <p class="text-xs text-gray-500">{{ __('reporting::trends.daily') }}</p>
    </section>

    @if ($metric->url() !== null)
        <p><a href="{{ $metric->url() }}" class="font-medium text-blue-700 underline">{{ __('reporting::trends.open_list') }}</a></p>
    @endif
</div>
