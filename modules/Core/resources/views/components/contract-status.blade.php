@props(['status'])

<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700' => in_array($status, [\Modules\Core\Domain\Enums\ContractStatus::Upcoming, \Modules\Core\Domain\Enums\ContractStatus::Expired, \Modules\Core\Domain\Enums\ContractStatus::Terminated], true),
    'bg-green-50 text-green-800' => $status === \Modules\Core\Domain\Enums\ContractStatus::Active,
    'bg-amber-50 text-amber-800' => $status === \Modules\Core\Domain\Enums\ContractStatus::Expiring,
])>{{ $status->label() }}</span>
