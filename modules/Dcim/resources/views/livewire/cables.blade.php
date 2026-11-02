<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('dcim::cabling.cables') }}</h1>
            <p class="text-sm text-gray-600">{{ __('dcim::cabling.cables_intro') }}</p>
        </div>
        <div>
            <label for="q" class="sr-only">{{ __('dcim::cabling.search') }}</label>
            <input id="q" type="search" wire:model.live.debounce.300ms="q" placeholder="{{ __('dcim::cabling.search') }}" class="rounded-md border-gray-300 text-sm">
        </div>
    </div>
    <div class="overflow-x-auto rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.fields.label') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.fields.type') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.end_a') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::cabling.end_b') }}</th>
                    <th scope="col" class="py-2 font-medium">{{ __('dcim::cabling.fields.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($cables as $cable)
                    <tr wire:key="cable-{{ $cable->id }}">
                        <td class="py-2 pr-4 font-medium">{{ $cable->displayLabel() }}</td>
                        <td class="py-2 pr-4">{{ collect([$cable->type->label(), $cable->colour, $cable->length_m ? $cable->length_m.' m' : null])->filter()->implode(' · ') }}</td>
                        @foreach ($cable->ends as $end)
                            <td wire:key="end-{{ $cable->id }}-{{ $end->end }}" class="py-2 pr-4"><x-dcim::port-ref :port="$end->port" :labels="$labels" /></td>
                        @endforeach
                        <td class="py-2">{{ $cable->status->label() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-2 text-gray-600">{{ __('dcim::cabling.no_cables') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $cables->links() }}</div>
    </div>
</div>
