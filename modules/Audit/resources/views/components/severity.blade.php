@props(['severity'])
<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-semibold',
    'bg-red-100 text-red-900' => $severity === \Modules\Audit\Domain\Enums\FindingSeverity::MajorNonconformity,
    'bg-orange-100 text-orange-900' => $severity === \Modules\Audit\Domain\Enums\FindingSeverity::MinorNonconformity,
    'bg-yellow-100 text-yellow-900' => $severity === \Modules\Audit\Domain\Enums\FindingSeverity::Observation,
    'bg-blue-100 text-blue-900' => $severity === \Modules\Audit\Domain\Enums\FindingSeverity::Opportunity,
])>{{ $severity->label() }}</span>
