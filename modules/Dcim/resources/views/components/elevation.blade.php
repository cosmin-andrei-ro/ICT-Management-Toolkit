@props(['blocks', 'face', 'assets', 'canManage', 'height'])
<figure class="space-y-1">
    <figcaption class="text-sm font-semibold">{{ $face->label() }}</figcaption>
    <ol class="grid rounded border-4 border-gray-700 bg-gray-700" style="grid-template-rows: repeat({{ $height }}, 1.5rem);" aria-label="{{ __('dcim::racks.elevation_of', ['face' => $face->label()]) }}">
        @foreach ($blocks as $block)
            @php($span = $block['top'] - $block['bottom'] + 1)
            <li wire:key="{{ $face->value }}-{{ $block['top'] }}" class="flex min-w-0 items-stretch border-b border-gray-700 text-xs" style="grid-row: span {{ $span }};">
                <span class="w-14 shrink-0 bg-gray-800 px-1 text-right leading-6 text-gray-300 tabular-nums">{{ $span > 1 ? 'U'.$block['bottom'].'–'.$block['top'] : 'U'.$block['top'] }}</span>
                @if ($block['mount'] !== null)
                    @php($asset = $assets[$block['mount']->asset_id] ?? null)
                    <span @class(['flex min-w-0 flex-1 items-center gap-1 px-2', 'bg-blue-100 text-blue-950' => $block['mount']->face === $face, 'bg-blue-50 text-blue-900' => $block['mount']->face !== $face])>
                        <a href="{{ $asset?->url ?? '#' }}" class="truncate font-medium underline" title="{{ $asset?->label() }}">{{ $asset?->label() ?? __('dcim::racks.unknown_asset') }}</a>
                        @if ($block['mount']->face !== $face)
                            <span class="shrink-0 text-[10px] text-gray-600">{{ __('dcim::racks.from_other_side') }}</span>
                        @endif
                    </span>
                @elseif ($canManage)
                    <button type="button" wire:click="startAt({{ $block['top'] }}, '{{ $face->value }}')" class="flex-1 bg-white px-2 text-left text-gray-400 hover:bg-green-50 hover:text-green-800">
                        <span class="sr-only">{{ __('dcim::racks.mount_at', ['unit' => $block['top'], 'face' => $face->label()]) }}</span>
                        <span aria-hidden="true">+</span>
                    </button>
                @else
                    <span class="flex-1 bg-white"><span class="sr-only">{{ __('dcim::racks.free') }}</span></span>
                @endif
            </li>
        @endforeach
    </ol>
</figure>
