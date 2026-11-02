@props(['status'])
<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700' => in_array($status, [\Modules\Applications\Domain\Enums\SheetStatus::Draft, \Modules\Applications\Domain\Enums\SheetStatus::Superseded], true),
    'bg-blue-50 text-blue-800' => $status === \Modules\Applications\Domain\Enums\SheetStatus::Submitted,
    'bg-amber-50 text-amber-800' => $status === \Modules\Applications\Domain\Enums\SheetStatus::Returned,
    'bg-green-50 text-green-800' => $status === \Modules\Applications\Domain\Enums\SheetStatus::Approved,
])>{{ $status->label() }}</span>
