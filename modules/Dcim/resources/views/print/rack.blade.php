@php($title = __('dcim::racks.rack', ['name' => $rack->name]))
<x-core::printable :title="$title" :pdf="$pdf" :styles="$styles ?? ''" :back-url="route('dcim.racks.show', $rack)" :back-label="'← '.$title" :pdf-url="$pdfAvailable ? route('dcim.racks.pdf', $rack) : null">
    <main class="mx-auto max-w-4xl space-y-6 bg-white p-8 text-sm print:max-w-none print:p-0">
        <header class="space-y-1 border-b border-gray-300 pb-4">
            <p class="text-gray-600">{{ $room }}</p>
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            <p class="text-gray-600">
                @if ($rack->row) {{ __('dcim::racks.row_value', ['row' => $rack->row]) }} · @endif
                @if ($rack->position) {{ __('dcim::racks.position_value', ['position' => $rack->position]) }} · @endif
                {{ __('dcim::racks.occupancy', ['used' => $used, 'height' => $rack->height_u, 'percent' => (int) round($used / max(1, $rack->height_u) * 100)]) }}
                · {{ $rack->max_power_watts ? __('dcim::racks.power_of', ['used' => $power, 'max' => $rack->max_power_watts]) : __('dcim::racks.power_only', ['used' => $power]) }}
            </p>
            <p class="text-gray-600">{{ __('dcim::reports.sheet.printed', ['date' => now()->timezone(config()->string('app.display_timezone'))->format('Y-m-d H:i')]) }}</p>
        </header>

        <div class="grid grid-cols-2 gap-6 break-inside-avoid">
            <x-dcim::elevation :blocks="$front" :face="\Modules\Dcim\Domain\Enums\MountFace::Front" :assets="$assets" :can-manage="false" :height="$rack->height_u" />
            <x-dcim::elevation :blocks="$rear" :face="\Modules\Dcim\Domain\Enums\MountFace::Rear" :assets="$assets" :can-manage="false" :height="$rack->height_u" />
        </div>

        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ __('dcim::racks.mounted') }}</h2>
            <table class="min-w-full text-left">
                <thead class="text-xs text-gray-500">
                    <tr>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ __('dcim::reports.export.units') }}</th>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ __('dcim::racks.fields.asset') }}</th>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ __('dcim::reports.export.serial') }}</th>
                        <th scope="col" class="py-1 pr-3 text-right font-medium">{{ __('dcim::reports.export.watts') }}</th>
                        <th scope="col" class="py-1 font-medium">{{ __('dcim::reports.export.supplies') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($mounts as $mount)
                        @php($asset = $assets[$mount->asset_id] ?? null)
                        <tr>
                            <td class="whitespace-nowrap py-1 pr-3">{{ $mount->unitsLabel() }} · {{ $mount->face->label() }}</td>
                            <td class="py-1 pr-3">{{ $asset?->label() ?? __('dcim::racks.unknown_asset') }}@if ($asset?->model) <span class="text-gray-500">· {{ trim($asset->manufacturer.' '.$asset->model) }}</span>@endif</td>
                            <td class="py-1 pr-3">{{ $asset?->serialNumber ?? '—' }}</td>
                            <td class="py-1 pr-3 text-right tabular-nums">{{ $mount->power_watts ?? '—' }}</td>
                            <td class="py-1">
                                {{ implode(', ', $supplies[$mount->id] ?? []) ?: '—' }}
                                @if (($redundant[$mount->id] ?? null) === false) <span class="text-xs font-medium text-amber-800">· {{ __('dcim::power.single_fed') }}</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold">{{ __('dcim::cabling.cables') }}</h2>
            @if ($cables->isEmpty())
                <p class="text-gray-600">{{ __('dcim::cabling.no_cables') }}</p>
            @else
                <table class="min-w-full text-left">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-1 pr-3 font-medium">{{ __('dcim::cabling.fields.label') }}</th>
                            <th scope="col" class="py-1 pr-3 font-medium">{{ __('dcim::cabling.end_a') }}</th>
                            <th scope="col" class="py-1 pr-3 font-medium">{{ __('dcim::cabling.end_b') }}</th>
                            <th scope="col" class="py-1 font-medium">{{ __('dcim::cabling.fields.type') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($cables as $cable)
                            <tr>
                                <td class="py-1 pr-3 font-medium">{{ $cable->displayLabel() }}</td>
                                @foreach ($cable->ends as $end)
                                    <td class="py-1 pr-3">{{ ($assets[$end->port->mount->asset_id] ?? null)?->label() }} · {{ $end->port->name }}@if ($end->port->mount->rack_id !== $rack->id) <span class="text-gray-500">({{ __('dcim::racks.rack', ['name' => $end->port->mount->rack->name]) }})</span>@endif</td>
                                @endforeach
                                <td class="py-1">{{ collect([$cable->type->label(), $cable->colour, $cable->length_m ? $cable->length_m.' m' : null])->filter()->implode(' · ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        @if ($rack->notes) <p class="whitespace-pre-line text-gray-700">{{ $rack->notes }}</p> @endif
    </main>
</x-core::printable>
