<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('ticketing::ui.portal.title') }}</h1>
        <a href="{{ route('ticketing.portal.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">{{ __('ticketing::ui.portal.new') }}</a>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <span>{{ __('ticketing::ui.show') }}</span>
        <select wire:model.live="show" class="rounded-md border-gray-300 text-sm">
            <option value="open">{{ __('ticketing::ui.filters.open') }}</option>
            <option value="closed">{{ __('ticketing::ui.filters.closed') }}</option>
            <option value="all">{{ __('ticketing::ui.filters.all') }}</option>
        </select>
    </label>

    <div class="overflow-hidden rounded-lg bg-white ring-1 ring-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-600">
                <tr>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.number') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.title') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.status') }}</th>
                    <th class="px-4 py-2">{{ __('ticketing::ui.fields.updated') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($tickets as $ticket)
                    <tr wire:key="ticket-{{ $ticket->id }}">
                        <td class="px-4 py-2 font-mono"><a href="{{ route('ticketing.portal.show', $ticket) }}" class="text-blue-700 underline">{{ $ticket->number }}</a></td>
                        <td class="px-4 py-2">{{ $ticket->title }}</td>
                        <td class="px-4 py-2">{{ $ticket->status->label() }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('ticketing::ui.portal.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tickets->links() }}
</div>
