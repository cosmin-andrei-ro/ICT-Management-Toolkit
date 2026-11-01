@props(['ticket'])

<span @class([
    'inline-flex items-center rounded px-2 py-0.5 text-xs font-medium',
    'bg-red-100 text-red-800' => $ticket->priority->value === 'critical',
    'bg-orange-100 text-orange-800' => $ticket->priority->value === 'high',
    'bg-yellow-100 text-yellow-800' => $ticket->priority->value === 'medium',
    'bg-gray-100 text-gray-700' => $ticket->priority->value === 'low',
])>{{ $ticket->priority->label() }}</span>
<span class="inline-flex items-center rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-800">{{ $ticket->status->label() }}</span>
