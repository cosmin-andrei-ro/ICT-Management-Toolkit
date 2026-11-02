@props(['status'])

<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700' => in_array($status, [\Modules\Bcm\Domain\Enums\VersionStatus::Draft, \Modules\Bcm\Domain\Enums\VersionStatus::Superseded], true),
    'bg-blue-50 text-blue-800' => $status === \Modules\Bcm\Domain\Enums\VersionStatus::Submitted,
    'bg-amber-50 text-amber-800' => $status === \Modules\Bcm\Domain\Enums\VersionStatus::Returned,
    'bg-green-50 text-green-800' => $status === \Modules\Bcm\Domain\Enums\VersionStatus::Approved,
])>{{ $status->label() }}</span>
