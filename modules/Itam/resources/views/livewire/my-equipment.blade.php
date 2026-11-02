<div class="mx-auto max-w-3xl space-y-6">
    <header class="space-y-1">
        <h1 class="text-xl font-semibold">{{ __('itam::assets.my.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('itam::assets.my.intro') }}</p>
    </header>

    <ul class="divide-y divide-gray-100 rounded-lg bg-white ring-1 ring-gray-200">
        @forelse ($assets as $asset)
            <li wire:key="asset-{{ $asset->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                    <p class="font-medium">{{ $asset->displayName() }}</p>
                    <p class="text-gray-500">
                        {{ $asset->category->name }} · <span class="font-mono">{{ $asset->asset_tag }}</span>
                        @if ($asset->serial_number) · <span class="font-mono">{{ $asset->serial_number }}</span> @endif
                    </p>
                </div>
                <div class="text-right text-xs text-gray-500">
                    <x-itam::status :status="$asset->status" />
                    @if (isset($since[$asset->id]))
                        <p class="mt-1">{{ __('itam::assets.my.since') }} {{ \Illuminate\Support\Carbon::parse($since[$asset->id])->toDateString() }}</p>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('itam::assets.my.empty') }}</li>
        @endforelse
    </ul>
</div>
