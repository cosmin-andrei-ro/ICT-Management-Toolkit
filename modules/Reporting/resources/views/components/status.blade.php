@props(['status'])
@php($neutral = $status === \Modules\Core\Domain\Metrics\MetricStatus::Neutral)
@unless ($neutral)
    {{-- Colour with its words, never colour alone. --}}
    <span @class([
        'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium ring-1',
        'bg-green-50 text-green-800 ring-green-200' => $status === \Modules\Core\Domain\Metrics\MetricStatus::Good,
        'bg-amber-50 text-amber-900 ring-amber-300' => $status === \Modules\Core\Domain\Metrics\MetricStatus::Warning,
        'bg-red-50 text-red-800 ring-red-300' => $status === \Modules\Core\Domain\Metrics\MetricStatus::Alert,
    ])>{{ __('reporting::dashboard.status.'.$status->value) }}</span>
@else
    <span class="text-xs text-gray-500">—</span>
@endunless
