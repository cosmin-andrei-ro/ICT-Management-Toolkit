<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('ticketing::changes.ui.calendar') }}</h1>
            <p class="text-sm text-gray-600">{{ __('ticketing::changes.ui.calendar_range', ['from' => $start->format('Y-m-d'), 'until' => $end->subDay()->format('Y-m-d'), 'timezone' => $timezone]) }}</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="date" wire:model.live="from" class="rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::changes.ui.from') }}">
            @can('createChange', \Modules\Ticketing\Domain\Models\Ticket::class)
                <a href="{{ route('ticketing.changes.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('ticketing::changes.ui.new') }}</a>
            @endcan
        </div>
    </div>

    @forelse ($byDay as $day => $changes)
        <section wire:key="day-{{ $day }}" class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 class="mb-2 font-semibold">{{ \Carbon\CarbonImmutable::parse($day)->isoFormat('dddd, D MMMM YYYY') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($changes as $change)
                    <li wire:key="change-{{ $change->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div>
                            <span class="font-mono text-gray-500">{{ $change->changeDetails?->planned_start?->timezone($timezone)->format('H:i') }}–{{ $change->changeDetails?->planned_end?->timezone($timezone)->format('H:i') }}</span>
                            <a href="{{ route('ticketing.desk.show', $change) }}" class="ml-2 text-blue-700 underline">{{ $change->number }} {{ $change->title }}</a>
                        </div>
                        <div class="flex gap-2 text-xs">
                            <span class="rounded bg-gray-100 px-2 py-0.5">{{ $change->changeDetails?->change_type->label() }}</span>
                            <span class="rounded bg-gray-100 px-2 py-0.5">{{ $change->changeDetails?->approval_status->label() }}</span>
                            @if (in_array($change->id, $conflicting, true))
                                <span class="rounded bg-amber-100 px-2 py-0.5 font-medium text-amber-800">{{ __('ticketing::changes.ui.overlaps') }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-sm text-gray-500">{{ __('ticketing::changes.ui.calendar_empty') }}</p>
    @endforelse
</div>
