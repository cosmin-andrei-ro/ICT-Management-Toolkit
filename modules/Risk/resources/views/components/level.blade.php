@props(['level', 'score' => null])
@if ($level)
    <span @class([
        'inline-block rounded px-1.5 py-0.5 text-xs font-semibold',
        'bg-green-100 text-green-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Low,
        'bg-yellow-100 text-yellow-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Medium,
        'bg-orange-100 text-orange-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::High,
        'bg-red-100 text-red-900' => $level === \Modules\Risk\Domain\Enums\RiskLevel::Critical,
    ])>{{ $level->label().($score !== null ? ' · '.$score : '') }}</span>
@else
    <span class="text-xs text-gray-500">{{ __('risk::risks.ui.not_assessed') }}</span>
@endif
