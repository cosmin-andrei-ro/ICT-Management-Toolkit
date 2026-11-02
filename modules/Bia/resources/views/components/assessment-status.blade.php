@props(['status'])

<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700' => in_array($status, [\Modules\Bia\Domain\Enums\AssessmentStatus::Draft, \Modules\Bia\Domain\Enums\AssessmentStatus::Superseded], true),
    'bg-blue-50 text-blue-800' => $status === \Modules\Bia\Domain\Enums\AssessmentStatus::Submitted,
    'bg-amber-50 text-amber-800' => $status === \Modules\Bia\Domain\Enums\AssessmentStatus::Returned,
    'bg-green-50 text-green-800' => $status === \Modules\Bia\Domain\Enums\AssessmentStatus::Approved,
])>{{ $status->label() }}</span>
