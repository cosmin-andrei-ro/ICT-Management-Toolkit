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
                <div class="space-y-1 text-right text-xs text-gray-500">
                    <x-itam::status :status="$asset->status" />
                    @php($assignment = $assignments->get($asset->id))
                    @if ($assignment)
                        <p>{{ __('itam::assets.my.since') }} {{ $assignment->assigned_at->toDateString() }} · <a href="{{ route('itam.handover', $assignment->id) }}" class="text-blue-700 underline">{{ __('itam::handover.protocol') }}</a></p>
                        @if ($assignment->acknowledged_at)
                            <p class="text-green-700">{{ __('itam::handover.confirmed') }}</p>
                        @else
                            <p class="text-amber-700">{{ __('itam::handover.to_confirm') }}</p>
                            <button type="button" wire:click="confirmReceipt('{{ $assignment->id }}')" class="rounded-md bg-gray-900 px-3 py-1 text-xs font-semibold text-white">{{ __('itam::handover.confirm') }}</button>
                        @endif
                    @endif
                </div>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-sm text-gray-500">{{ __('itam::assets.my.empty') }}</li>
        @endforelse
    </ul>

    @if ($software->isNotEmpty())
        <section aria-labelledby="software-heading" class="space-y-2">
            <h2 id="software-heading" class="font-semibold">{{ __('itam::licenses.ui.my_software') }}</h2>
            <ul class="divide-y divide-gray-100 rounded-lg bg-white text-sm ring-1 ring-gray-200">
                @foreach ($software as $product)
                    <li wire:key="software-{{ $loop->index }}" class="px-4 py-2">{{ $product }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
