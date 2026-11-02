<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('core::contracts.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('core::contracts.ui.intro') }}</p>
        </div>
        @can('create', \Modules\Core\Domain\Models\Contract::class)
            <a href="{{ route('core.contracts.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('core::contracts.ui.new') }}</a>
        @endcan
    </div>

    <div class="flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('core::contracts.ui.search') }}" aria-label="{{ __('core::contracts.ui.search') }}" class="rounded-md border-gray-300 text-sm">
        <select wire:model.live="status" aria-label="{{ __('core::contracts.fields.status') }}" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('core::contracts.ui.all_statuses') }}</option>
            @foreach ($statuses as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="type" aria-label="{{ __('core::contracts.fields.type') }}" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('core::contracts.ui.all_types') }}</option>
            @foreach ($types as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="supplier" aria-label="{{ __('core::contracts.fields.supplier') }}" class="rounded-md border-gray-300 text-sm">
            <option value="">{{ __('core::contracts.ui.all_suppliers') }}</option>
            @foreach ($suppliers as $option)
                <option value="{{ $option->id }}" wire:key="supplier-{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('core::contracts.fields.title') }}</th>
                    <th class="px-3 py-2">{{ __('core::contracts.fields.supplier') }}</th>
                    <th class="px-3 py-2">{{ __('core::contracts.fields.type') }}</th>
                    <th class="px-3 py-2">{{ __('core::contracts.fields.deadline') }}</th>
                    <th class="px-3 py-2">{{ __('core::contracts.fields.end_date') }}</th>
                    <th class="px-3 py-2">{{ __('core::contracts.fields.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contracts as $contract)
                    <tr wire:key="contract-{{ $contract->id }}" class="border-t border-gray-100">
                        <td class="px-3 py-2">
                            <a href="{{ route('core.contracts.show', $contract) }}" class="font-medium text-blue-700 underline">{{ $contract->title }}</a>
                            @if ($contract->reference) <span class="ml-1 font-mono text-xs text-gray-500">{{ $contract->reference }}</span> @endif
                        </td>
                        <td class="px-3 py-2">{{ $contract->supplier->name }}</td>
                        <td class="px-3 py-2">{{ $contract->type->label() }}</td>
                        <td class="px-3 py-2">{{ $contract->deadline()?->toDateString() ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $contract->end_date?->toDateString() ?? __('core::contracts.ui.no_end') }}</td>
                        <td class="px-3 py-2"><x-core::contract-status :status="$contract->statusOn($today, $expiringDays)" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('core::contracts.ui.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $contracts->links() }}
</div>
