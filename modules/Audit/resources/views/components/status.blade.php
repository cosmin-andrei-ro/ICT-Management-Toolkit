@props(['status'])
<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-semibold',
    'bg-yellow-100 text-yellow-900' => $status === \Modules\Audit\Domain\Enums\ControlStatus::Planned,
    'bg-orange-100 text-orange-900' => $status === \Modules\Audit\Domain\Enums\ControlStatus::Partial,
    'bg-green-100 text-green-900' => $status === \Modules\Audit\Domain\Enums\ControlStatus::Implemented,
    'bg-gray-100 text-gray-700' => $status === \Modules\Audit\Domain\Enums\ControlStatus::Retired,
])>{{ $status->label() }}</span>
