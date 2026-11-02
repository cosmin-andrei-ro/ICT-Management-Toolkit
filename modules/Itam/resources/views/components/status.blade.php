@props(['status'])

<span @class([
    'inline-block rounded px-1.5 py-0.5 text-xs font-medium',
    'bg-green-50 text-green-800' => $status === \Modules\Itam\Domain\Enums\AssetStatus::Deployed,
    'bg-blue-50 text-blue-800' => in_array($status, [\Modules\Itam\Domain\Enums\AssetStatus::InStock, \Modules\Itam\Domain\Enums\AssetStatus::Ordered], true),
    'bg-amber-50 text-amber-800' => $status === \Modules\Itam\Domain\Enums\AssetStatus::InRepair,
    'bg-red-50 text-red-800' => $status === \Modules\Itam\Domain\Enums\AssetStatus::Lost,
    'bg-gray-100 text-gray-700' => in_array($status, [\Modules\Itam\Domain\Enums\AssetStatus::Retired, \Modules\Itam\Domain\Enums\AssetStatus::Disposed], true),
])>{{ $status->label() }}</span>
