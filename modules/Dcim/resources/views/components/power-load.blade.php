@props(['load', 'capacity', 'warn'])
@php($normal = \Modules\Dcim\Application\PowerGrid::percent($load['normal'], $capacity))
@php($worst = \Modules\Dcim\Application\PowerGrid::percent($load['worst'], $capacity))
@php($level = match (true) { $normal === null => 'none', $normal > 100 || $worst > 100 => 'alert', $normal >= $warn || $worst >= $warn => 'warning', default => 'good' })
<div class="space-y-1 text-xs">
    @if ($normal !== null)
        <div class="h-2 w-full rounded bg-gray-100" role="img" aria-label="{{ __('dcim::power.load_of', ['load' => (int) round($load['normal']), 'capacity' => $capacity, 'percent' => $normal]) }}">
            <div @class(['h-2 rounded', 'bg-green-600' => $level === 'good', 'bg-amber-500' => $level === 'warning', 'bg-red-600' => $level === 'alert']) style="width: {{ min(100, $normal) }}%"></div>
        </div>
    @endif
    <p class="text-gray-700">
        {{ $normal !== null ? __('dcim::power.load_of', ['load' => (int) round($load['normal']), 'capacity' => $capacity, 'percent' => $normal]) : __('dcim::power.load_only', ['load' => (int) round($load['normal'])]) }}
        @if ($level === 'alert') <span class="font-medium text-red-700">· {{ __('dcim::power.overload') }}</span> @elseif ($level === 'warning') <span class="font-medium text-amber-800">· {{ __('dcim::power.high') }}</span> @endif
    </p>
    @if ($load['worst'] > $load['normal'])
        <p class="text-gray-500">{{ $worst !== null ? __('dcim::power.worst_of', ['load' => (int) round($load['worst']), 'percent' => $worst]) : __('dcim::power.worst_only', ['load' => (int) round($load['worst'])]) }}</p>
    @endif
    @if ($load['unknown'] > 0)
        <p class="text-amber-800">{{ trans_choice('dcim::power.unknown_draw', $load['unknown'], ['count' => $load['unknown']]) }}</p>
    @endif
</div>
