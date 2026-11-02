<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ __('core::suppliers.title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('core::suppliers.ui.intro') }}</p>
        </div>
        @can('create', \Modules\Core\Domain\Models\Supplier::class)
            <a href="{{ route('core.suppliers.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('core::suppliers.ui.new') }}</a>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <input type="search" wire:model.live.debounce.400ms="search" maxlength="200" placeholder="{{ __('core::suppliers.ui.search') }}" aria-label="{{ __('core::suppliers.ui.search') }}" class="rounded-md border-gray-300 text-sm">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="inactive" class="rounded border-gray-300">
            {{ __('core::suppliers.ui.show_inactive') }}
        </label>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-2">{{ __('core::suppliers.fields.name') }}</th>
                    <th class="px-3 py-2">{{ __('core::suppliers.fields.support_phone') }}</th>
                    <th class="px-3 py-2">{{ __('core::suppliers.fields.support_email') }}</th>
                    <th class="px-3 py-2">{{ __('core::suppliers.fields.customer_number') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr wire:key="supplier-{{ $supplier->id }}" class="border-t border-gray-100">
                        <td class="px-3 py-2">
                            <a href="{{ route('core.suppliers.show', $supplier) }}" class="font-medium text-blue-700 underline">{{ $supplier->name }}</a>
                            @unless ($supplier->is_active) <span class="ml-1 text-xs text-gray-500">{{ __('core::suppliers.ui.inactive') }}</span> @endunless
                        </td>
                        <td class="px-3 py-2">{{ $supplier->support_phone ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $supplier->support_email ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $supplier->customer_number ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">{{ __('core::suppliers.ui.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $suppliers->links() }}
</div>
