@props(['timer'])

@if ($timer === null)
    <span class="text-gray-400">—</span>
@elseif ($timer->isCompleted())
    <span @class([
        'inline-flex rounded px-2 py-0.5 text-xs font-medium',
        'bg-green-50 text-green-800' => $timer->outcome?->value === 'met',
        'bg-red-50 text-red-800' => $timer->outcome?->value === 'breached',
        'bg-gray-100 text-gray-600' => $timer->outcome?->value === 'cancelled',
    ])>{{ $timer->outcome?->label() }}</span>
@elseif (! $timer->isRunning())
    <span class="inline-flex rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('ticketing::sla.paused') }}</span>
@else
    <time datetime="{{ $timer->due_at?->toIso8601String() }}" title="{{ $timer->due_at?->timezone(config('app.display_timezone', 'Europe/Bucharest'))->format('Y-m-d H:i') }}" @class([
        'inline-flex rounded px-2 py-0.5 text-xs font-medium',
        'bg-red-100 text-red-800' => $timer->breached_at !== null,
        'bg-amber-100 text-amber-800' => $timer->breached_at === null && $timer->warned_at !== null,
        'bg-gray-100 text-gray-700' => $timer->breached_at === null && $timer->warned_at === null,
    ])>
        @if ($timer->breached_at !== null)
            {{ __('ticketing::sla.overdue_since', ['time' => $timer->due_at?->diffForHumans(short: true)]) }}
        @else
            {{ __('ticketing::sla.due_in', ['time' => $timer->due_at?->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
        @endif
    </time>
@endif
