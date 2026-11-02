@props(['state'])
<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-semibold',
    'bg-gray-100 text-gray-700' => in_array($state, [\Modules\Audit\Domain\Enums\RequirementState::Undecided, \Modules\Audit\Domain\Enums\RequirementState::NotApplicable], true),
    'bg-red-100 text-red-900' => $state === \Modules\Audit\Domain\Enums\RequirementState::NotCovered,
    'bg-yellow-100 text-yellow-900' => $state === \Modules\Audit\Domain\Enums\RequirementState::Planned,
    'bg-orange-100 text-orange-900' => $state === \Modules\Audit\Domain\Enums\RequirementState::Partial,
    'bg-green-100 text-green-900' => $state === \Modules\Audit\Domain\Enums\RequirementState::Implemented,
])>{{ $state->label() }}</span>
