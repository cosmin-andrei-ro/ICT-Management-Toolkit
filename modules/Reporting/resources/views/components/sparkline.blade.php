@props(['series', 'label'])
@php($chart = \Modules\Reporting\Application\Charts\LineChart::make($series, 100, 24))
@if (count($chart->points) >= 2)
    <svg viewBox="0 -2 100 28" preserveAspectRatio="none" class="h-6 w-full" role="img" aria-label="{{ $label }}">
        <title>{{ $label }}</title>
        @foreach ($chart->paths as $path)
            <path d="{{ $path }}" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        @endforeach
    </svg>
@endif
