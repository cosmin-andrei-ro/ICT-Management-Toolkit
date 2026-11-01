<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('cmdb::items.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('cmdb::items.ui.intro') }}</p>
        </div>
        @can('create', \Modules\Cmdb\Domain\Models\ConfigurationItem::class)
            <a href="{{ route('cmdb.items.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('cmdb::items.ui.new') }}</a>
        @endcan
    </div>

    <div class="flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('cmdb::items.ui.search') }}" aria-label="{{ __('cmdb::items.ui.search') }}" class="rounded-md border-gray-300 text-sm">
        <select wire:model.live="type" aria-label="{{ __('cmdb::items.fields.type') }}" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('cmdb::items.ui.all_types') }}</option>
            @foreach ($types as $option)
                <option value="{{ $option->id }}" wire:key="type-{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" aria-label="{{ __('cmdb::items.fields.status') }}" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('cmdb::items.ui.all_statuses') }}</option>
            @foreach ($statuses as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="environment" aria-label="{{ __('cmdb::items.fields.environment') }}" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('cmdb::items.ui.all_environments') }}</option>
            @foreach ($environments as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="criticality" aria-label="{{ __('cmdb::items.fields.criticality') }}" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('cmdb::items.ui.all_criticalities') }}</option>
            @foreach ($criticalities as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('cmdb::items.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('cmdb::items.fields.type') }}</th>
                    <th class="px-3 py-2">{{ __('cmdb::items.fields.status') }}</th>
                    <th class="px-3 py-2">{{ __('cmdb::items.fields.environment') }}</th>
                    <th class="px-3 py-2">{{ __('cmdb::items.fields.criticality') }}</th>
                    <th class="px-3 py-2">{{ __('cmdb::items.fields.team') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr wire:key="item-{{ $item->id }}" class="border-t border-gray-100">
                        <td class="px-3 py-2">
                            <a href="{{ route('cmdb.items.show', $item) }}" class="font-medium text-blue-700 underline">{{ $item->name }}</a>
                            @if ($item->identifier) <span class="ml-1 font-mono text-xs text-gray-500">{{ $item->identifier }}</span> @endif
                        </td>
                        <td class="px-3 py-2">{{ $item->type->name }}</td>
                        <td class="px-3 py-2">{{ $item->status->label() }}</td>
                        <td class="px-3 py-2">{{ $item->environment?->label() ?? '—' }}</td>
                        <td class="px-3 py-2"><x-cmdb::criticality :value="$item->criticality" /></td>
                        <td class="px-3 py-2">{{ $item->team?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('cmdb::items.ui.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $items->links() }}
</div>
