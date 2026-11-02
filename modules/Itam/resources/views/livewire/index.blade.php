<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('itam::assets.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('itam::assets.ui.intro') }}</p>
        </div>
        @can('create', \Modules\Itam\Domain\Models\Asset::class)
            <a href="{{ route('itam.assets.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('itam::assets.ui.new') }}</a>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('itam::assets.ui.search') }}" aria-label="{{ __('itam::assets.ui.search') }}" class="w-72 rounded-md text-sm">
        <select wire:model.live="category" aria-label="{{ __('itam::assets.fields.category') }}" class="rounded-md text-sm">
            <option value="">{{ __('itam::assets.ui.all_categories') }}</option>
            @foreach ($categories as $option)
                <option value="{{ $option->id }}" wire:key="category-{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" aria-label="{{ __('itam::assets.fields.status') }}" class="rounded-md text-sm">
            <option value="active">{{ __('itam::assets.ui.active') }}</option>
            <option value="">{{ __('itam::assets.ui.all_statuses') }}</option>
            @foreach ($statuses as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="location" aria-label="{{ __('itam::assets.fields.location') }}" class="rounded-md text-sm">
            <option value="">{{ __('itam::assets.ui.all_locations') }}</option>
            @foreach ($locations as $id => $path)
                <option value="{{ $id }}" wire:key="location-{{ $id }}">{{ $path }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="warranty" class="rounded border-gray-300">
            {{ __('itam::assets.ui.warranty_ending') }}
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('itam::assets.fields.asset_tag') }}</th>
                    <th class="px-3 py-2">{{ __('itam::assets.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('itam::assets.fields.category') }}</th>
                    <th class="px-3 py-2">{{ __('itam::assets.fields.status') }}</th>
                    <th class="px-3 py-2">{{ __('itam::assets.fields.holder') }}</th>
                    <th class="px-3 py-2">{{ __('itam::assets.fields.location') }}</th>
                    <th class="px-3 py-2">{{ __('itam::assets.fields.warranty_ends_on') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assets as $asset)
                    <tr wire:key="asset-{{ $asset->id }}" class="border-t border-gray-100">
                        <td class="px-3 py-2"><a href="{{ route('itam.assets.show', $asset) }}" class="font-mono font-medium text-blue-700 underline">{{ $asset->asset_tag }}</a></td>
                        <td class="px-3 py-2">
                            {{ $asset->displayName() }}
                            @if ($asset->serial_number) <span class="block font-mono text-xs text-gray-500">{{ $asset->serial_number }}</span> @endif
                        </td>
                        <td class="px-3 py-2">{{ $asset->category->name }}</td>
                        <td class="px-3 py-2"><x-itam::status :status="$asset->status" /></td>
                        <td class="px-3 py-2">{{ $asset->holder?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $asset->location_id ? ($locations[$asset->location_id] ?? $asset->location?->name) : '—' }}</td>
                        <td class="px-3 py-2">{{ $asset->warranty_ends_on?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">{{ __('itam::assets.ui.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $assets->links() }}
</div>
