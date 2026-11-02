@props(['series', 'metric', 'label'])
@php($left = 48)
@php($top = 8)
@php($chart = \Modules\Reporting\Application\Charts\LineChart::make($series, 580, 200))
@php($dates = array_keys($series))
@php($axis = [0 => $dates[0] ?? null, (int) floor((count($dates) - 1) / 2) => $dates[(int) floor((count($dates) - 1) / 2)] ?? null, count($dates) - 1 => $dates[count($dates) - 1] ?? null])
@php($step = count($dates) > 1 ? 580 / (count($dates) - 1) : 0)
<svg viewBox="0 0 640 240" class="h-auto w-full" role="img" aria-label="{{ $label }}">
    <title>{{ $label }}</title>
    <g transform="translate({{ $left }} {{ $top }})">
        @foreach ($chart->ticks as $tick)
            <line x1="0" x2="580" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" stroke="#e5e7eb" stroke-width="1" />
            <text x="-8" y="{{ $tick['y'] }}" dy="4" text-anchor="end" fill="#6b7280" font-size="11">{{ $metric->format($tick['value']) }}</text>
        @endforeach
        @foreach ($axis as $index => $date)
            @if ($date !== null)
                <text x="{{ round($index * $step, 2) }}" y="222" text-anchor="{{ $index === 0 ? 'start' : ($index === count($dates) - 1 ? 'end' : 'middle') }}" fill="#6b7280" font-size="11">{{ \Carbon\CarbonImmutable::parse($date)->isoFormat('D MMM YYYY') }}</text>
            @endif
        @endforeach
        @foreach ($chart->paths as $path)
            <path d="{{ $path }}" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        @endforeach
        @if ($chart->points !== [])
            @php($last = $chart->points[count($chart->points) - 1])
            <circle cx="{{ $last['x'] }}" cy="{{ $last['y'] }}" r="4" fill="#1d4ed8" stroke="#ffffff" stroke-width="2" />
        @endif
        {{-- Colours as SVG attributes, not classes: the same in print and PDF. Wider invisible targets show a day's value on hover. --}}
        @foreach ($chart->points as $point)
            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ count($chart->points) > 120 ? 3 : 8 }}" fill="transparent">
                <title>{{ \Carbon\CarbonImmutable::parse($point['date'])->isoFormat('D MMM YYYY') }}: {{ $metric->format($point['value']) }}</title>
            </circle>
        @endforeach
    </g>
</svg>
