<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('dcim::ipam.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('dcim::ipam.intro') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label for="q" class="sr-only">{{ __('dcim::ipam.search') }}</label>
            <input id="q" type="search" wire:model.live.debounce.300ms="q" placeholder="{{ __('dcim::ipam.search') }}" class="rounded-md border-gray-300 text-sm">
            <a href="{{ route('dcim.ipam.vlans') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('dcim::ipam.vlans') }}</a>
            @can('create', \Modules\Dcim\Domain\Models\Rack::class)
                <a href="{{ route('dcim.ipam.subnets.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('dcim::ipam.new_subnet') }}</a>
            @endcan
        </div>
    </div>

    @if ($searchedAddress !== null)
        <p class="rounded-md bg-white p-3 text-sm ring-1 ring-gray-200">
            @if ($found)
                {{ __('dcim::ipam.address_found', ['address' => $found->host(), 'status' => $found->status->label()]) }}@if ($found->dns_name) · {{ $found->dns_name }}@endif
            @else
                {{ __('dcim::ipam.address_free', ['address' => $searchedAddress]) }}
            @endif
        </p>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.network') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.vlan') }}</th>
                    <th scope="col" class="py-2 pr-4 font-medium">{{ __('dcim::ipam.fields.purpose') }}</th>
                    <th scope="col" class="w-48 py-2 font-medium">{{ __('dcim::ipam.used') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($subnets as $subnet)
                    @php($usable = $subnet->parsed()->usable())
                    @php($percent = $usable ? (int) round($subnet->used / $usable * 100) : null)
                    <tr wire:key="subnet-{{ $subnet->id }}">
                        <td class="py-2 pr-4" style="padding-left: {{ (int) $subnet->depth * 1.5 }}rem"><a href="{{ route('dcim.ipam.subnets.show', $subnet) }}" class="font-mono font-medium text-blue-700 underline">{{ $subnet->network }}</a></td>
                        <td class="py-2 pr-4">{{ $subnet->vlan?->label() ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $subnet->purpose ?? '—' }}</td>
                        <td class="py-2">
                            @if ($percent !== null)
                                <div class="h-2 rounded bg-gray-100" role="img" aria-label="{{ __('dcim::ipam.usage', ['used' => $subnet->used, 'usable' => $usable, 'percent' => $percent]) }}"><div @class(['h-2 rounded', 'bg-blue-600' => $percent < 85, 'bg-amber-500' => $percent >= 85]) style="width: {{ min(100, $percent) }}%"></div></div>
                                <span class="text-xs text-gray-600">{{ __('dcim::ipam.usage', ['used' => $subnet->used, 'usable' => $usable, 'percent' => $percent]) }}</span>
                            @else
                                <span class="text-xs text-gray-600">{{ trans_choice('dcim::ipam.count', (int) $subnet->used, ['count' => $subnet->used]) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-2 text-gray-600">{{ __('dcim::ipam.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $subnets->links() }}</div>
    </div>
</div>
