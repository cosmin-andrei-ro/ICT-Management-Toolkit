@props(['value'])

<span @class([
    'rounded px-2 py-0.5 text-xs font-medium',
    'bg-gray-100 text-gray-700' => $value->value === 'low',
    'bg-blue-50 text-blue-800' => $value->value === 'medium',
    'bg-amber-100 text-amber-800' => $value->value === 'high',
    'bg-red-100 text-red-800' => $value->value === 'critical',
])>{{ $value->label() }}</span>
