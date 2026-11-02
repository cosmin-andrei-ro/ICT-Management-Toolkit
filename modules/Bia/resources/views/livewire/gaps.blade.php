@php($duration = fn (?int $minutes): string => \Modules\Core\Domain\Time\Duration::format($minutes))
<div class="space-y-6">
    <header class="space-y-1">
        <h1 class="text-xl font-semibold">{{ __('bia::gaps.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('bia::gaps.intro') }}</p>
    </header>

    @if (! $cmdbAvailable)
        <p class="rounded-lg bg-white p-6 text-sm text-gray-500 ring-1 ring-gray-200">{{ __('bia::gaps.cmdb_off') }}</p>
    @else
        <div class="flex flex-wrap items-center gap-4 text-sm">
            @foreach (['gap', 'unknown', 'met'] as $status)
                <span wire:key="count-{{ $status }}">{{ __('bia::gaps.counts.'.$status) }}: <strong>{{ $counts[$status] ?? 0 }}</strong></span>
            @endforeach
            <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="problems" class="rounded border-gray-300"> {{ __('bia::gaps.only_problems') }}</label>
        </div>
        @error('criticality') <p class="rounded-md bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

        <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2">{{ __('bia::gaps.item') }}</th>
                        <th class="px-3 py-2">{{ __('bia::gaps.required') }}</th>
                        <th class="px-3 py-2">{{ __('bia::gaps.capability') }}</th>
                        <th class="px-3 py-2">{{ __('bia::gaps.processes') }}</th>
                        <th class="px-3 py-2">{{ __('bia::gaps.criticality') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="gap-{{ $row->item->id }}" class="border-t border-gray-100 align-top">
                            <td class="px-3 py-2">
                                <a href="{{ $row->item->url }}" class="font-medium text-blue-700 underline">{{ $row->item->name }}</a>
                                <span class="block text-xs text-gray-500">{{ $row->item->typeName }}</span>
                                <span @class(['mt-1 inline-block rounded px-1.5 py-0.5 text-xs font-medium',
                                    'bg-red-50 text-red-800' => $row->status() === 'gap',
                                    'bg-amber-50 text-amber-800' => $row->status() === 'unknown',
                                    'bg-green-50 text-green-800' => $row->status() === 'met'])>{{ __('bia::gaps.statuses.'.$row->status()) }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <p>{{ __('bia::gaps.rto', ['value' => $duration($row->requiredRto)]) }}</p>
                                @if ($row->requiredRpo !== null) <p>{{ __('bia::gaps.rpo', ['value' => $duration($row->requiredRpo)]) }}</p> @endif
                            </td>
                            <td class="px-3 py-2">
                                <p @class(['text-red-700 font-medium' => $row->rtoStatus() === 'gap', 'text-amber-700' => $row->rtoStatus() === 'unknown'])>{{ __('bia::gaps.rto', ['value' => $duration($row->item->recoveryTimeMinutes)]) }}</p>
                                @if ($row->requiredRpo !== null)
                                    <p @class(['text-red-700 font-medium' => $row->rpoStatus() === 'gap', 'text-amber-700' => $row->rpoStatus() === 'unknown'])>{{ __('bia::gaps.rpo', ['value' => $duration($row->item->recoveryPointMinutes)]) }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <ul class="space-y-0.5">
                                    @foreach ($row->processes as $process)
                                        <li wire:key="gap-{{ $row->item->id }}-{{ $loop->index }}"><a href="{{ $process['url'] }}" class="text-blue-700 underline">{{ $process['name'] }}</a> <span class="text-xs text-gray-500">{{ $duration($process['rto']) }}@unless ($process['direct']) · {{ __('bia::gaps.via') }}@endunless</span></li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="px-3 py-2">
                                <p>{{ __('cmdb::items.criticalities.'.$row->item->criticality) }}</p>
                                @if ($row->criticalityDiffers())
                                    <p class="text-xs text-gray-500">{{ __('bia::gaps.suggested', ['criticality' => __('cmdb::items.criticalities.'.$row->suggestedCriticality)]) }}</p>
                                    @if ($canAlign)
                                        <button type="button" wire:click="alignCriticality('{{ $row->item->id }}')" class="mt-1 rounded-md bg-white px-2 py-0.5 text-xs font-semibold ring-1 ring-gray-300">{{ __('bia::gaps.align') }}</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('bia::gaps.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
