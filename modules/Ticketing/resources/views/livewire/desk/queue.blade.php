<div class="space-y-6">
    <h1 class="text-xl font-semibold">{{ __('ticketing::ui.desk.title') }}</h1>

    <div class="grid gap-3 rounded-lg bg-white p-4 ring-1 ring-gray-200 sm:grid-cols-2 lg:grid-cols-6">
        <label class="text-sm lg:col-span-2">
            <span class="block font-medium">{{ __('ticketing::ui.search') }}</span>
            <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('ticketing::ui.desk.search_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300">
        </label>
        <label class="text-sm">
            <span class="block font-medium">{{ __('ticketing::ui.fields.status') }}</span>
            <select wire:model.live="status" class="mt-1 block w-full rounded-md border-gray-300">
                <option value="open">{{ __('ticketing::ui.filters.open') }}</option>
                <option value="all">{{ __('ticketing::ui.filters.all') }}</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="block font-medium">{{ __('ticketing::ui.fields.queue') }}</span>
            <select wire:model.live="queue" class="mt-1 block w-full rounded-md border-gray-300">
                <option value="">{{ __('ticketing::ui.filters.all') }}</option>
                @foreach ($queues as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">
            <span class="block font-medium">{{ __('ticketing::sla.filter.label') }}</span>
            <select wire:model.live="sla" class="mt-1 block w-full rounded-md border-gray-300">
                <option value="any">{{ __('ticketing::sla.filter.any') }}</option>
                <option value="at_risk">{{ __('ticketing::sla.filter.at_risk') }}</option>
                <option value="breached">{{ __('ticketing::sla.filter.breached') }}</option>
            </select>
        </label>
        <label class="text-sm">
            <span class="block font-medium">{{ __('ticketing::ui.fields.assignee') }}</span>
            <select wire:model.live="assignee" class="mt-1 block w-full rounded-md border-gray-300">
                <option value="any">{{ __('ticketing::ui.filters.anyone') }}</option>
                <option value="me">{{ __('ticketing::ui.filters.me') }}</option>
                <option value="unassigned">{{ __('ticketing::ui.filters.unassigned') }}</option>
            </select>
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                <tr>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.number') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.title') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.priority') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.requester') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.queue') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.assignee') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::sla.due') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.age') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($tickets as $ticket)
                    <tr wire:key="ticket-{{ $ticket->id }}">
                        <td class="px-4 py-2 font-mono"><a href="{{ route('ticketing.desk.show', $ticket) }}" class="text-blue-700 underline">{{ $ticket->number }}</a></td>
                        <td class="px-4 py-2">{{ $ticket->title }}</td>
                        <td class="px-4 py-2"><x-ticketing::badges :ticket="$ticket" /></td>
                        <td class="px-4 py-2">{{ $ticket->requester->name }}</td>
                        <td class="px-4 py-2">{{ $ticket->queue?->name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $ticket->assignee?->name ?? __('ticketing::ui.filters.unassigned') }}</td>
                        <td class="px-4 py-2">
                            {{-- The response clock matters until the first reply, then the resolution clock. --}}
                            @php($response = $ticket->slaTimers->firstWhere('metric', \Modules\Ticketing\Domain\Sla\SlaMetric::Response))
                            <x-ticketing::sla-badge :timer="$response !== null && ! $response->isCompleted() ? $response : $ticket->slaTimers->firstWhere('metric', \Modules\Ticketing\Domain\Sla\SlaMetric::Resolution)" />
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $ticket->created_at?->diffForHumans(short: true) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('ticketing::ui.desk.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tickets->links() }}
</div>
