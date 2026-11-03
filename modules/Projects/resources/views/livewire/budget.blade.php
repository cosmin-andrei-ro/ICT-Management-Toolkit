@php($money = fn (?float $value): string => $value === null ? '—' : \Illuminate\Support\Number::format($value, 2).' '.($figures->currency ?? ''))
<section aria-labelledby="budget-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="budget-heading" class="text-sm font-semibold">{{ __('projects::budget.heading') }}</h2>
        @if ($canManage && ! $editingBudget)
            <button type="button" wire:click="editBudget" class="text-xs font-medium text-blue-700 underline">{{ __('projects::budget.set_budget') }}</button>
        @endif
    </div>

    @if ($editingBudget)
        <form wire:submit="saveBudget" class="flex flex-wrap items-end gap-3 text-sm" novalidate>
            <div>
                <label for="budgetAmount" class="block text-xs font-medium">{{ __('projects::budget.approved') }}</label>
                <input id="budgetAmount" type="number" step="0.01" min="0" wire:model="budgetAmount" class="mt-1 block w-40 rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="currency" class="block text-xs font-medium">{{ __('projects::budget.currency') }}</label>
                <input id="currency" type="text" maxlength="3" wire:model="currency" class="mt-1 block w-20 rounded-md border-gray-300 text-sm uppercase">
            </div>
            <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 font-semibold text-white">{{ __('projects::projects.save') }}</button>
            @foreach (['budgetAmount', 'currency'] as $field) @error($field) <p class="basis-full text-red-600">{{ $message }}</p> @enderror @endforeach
        </form>
    @endif

    <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div><dt class="text-xs text-gray-500">{{ __('projects::budget.approved') }}</dt><dd class="font-semibold tabular-nums">{{ $money($figures->budget) }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('projects::budget.spent') }}</dt><dd class="font-semibold tabular-nums">{{ $money($figures->spent) }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('projects::budget.remaining') }}</dt><dd @class(['font-semibold tabular-nums', 'text-red-700' => $figures->isOver()])>{{ $money($figures->remaining) }}</dd></div>
        <div><dt class="text-xs text-gray-500">{{ __('projects::budget.used') }}</dt><dd @class(['font-semibold', 'text-red-700' => $figures->isOver()])>{{ $figures->percent !== null ? $figures->percent.'%' : '—' }}@if ($figures->isOver()) · {{ __('projects::budget.over') }}@endif</dd></div>
    </dl>
    @if ($figures->percent !== null)
        <div class="h-2 rounded bg-gray-100" aria-hidden="true"><div @class(['h-2 rounded', 'bg-blue-600' => $figures->percent < 90, 'bg-amber-500' => $figures->percent >= 90 && ! $figures->isOver(), 'bg-red-600' => $figures->isOver()]) style="width: {{ min(100, $figures->percent) }}%"></div></div>
    @endif

    @if ($figures->spent != 0)
        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
            @foreach ($categories as $category)
                @if ($figures->byCategory[$category->value] != 0)
                    <li wire:key="by-{{ $category->value }}">{{ $category->label() }}: <span class="tabular-nums">{{ $money($figures->byCategory[$category->value]) }}</span></li>
                @endif
            @endforeach
        </ul>
    @endif

    @if ($costs->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ __('projects::budget.fields.incurred_on') }}</th>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ __('projects::budget.fields.description') }}</th>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ __('projects::budget.fields.category') }}</th>
                        <th scope="col" class="py-1 pr-3 text-right font-medium">{{ __('projects::budget.fields.amount') }}</th>
                        <th scope="col" class="py-1 font-medium"><span class="sr-only">{{ __('projects::projects.delete') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($costs as $cost)
                        <tr wire:key="cost-{{ $cost->id }}">
                            <td class="whitespace-nowrap py-1 pr-3">{{ $cost->incurred_on->toDateString() }}</td>
                            <td class="py-1 pr-3">{{ $cost->description }} @if ($cost->supplier || $cost->invoice_number) <span class="block text-xs text-gray-500">{{ collect([$cost->supplier?->name, $cost->invoice_number])->filter()->implode(' · ') }}</span> @endif</td>
                            <td class="py-1 pr-3">{{ $cost->category->label() }}</td>
                            <td class="py-1 pr-3 text-right tabular-nums">{{ $money((float) $cost->amount) }}</td>
                            <td class="py-1 text-right text-xs">
                                @if ($canManage)
                                    <button type="button" wire:click="deleteCost('{{ $cost->id }}')" wire:confirm="{{ __('projects::budget.delete_confirm') }}" class="font-medium text-red-700 underline">{{ __('projects::projects.delete') }}<span class="sr-only">: {{ $cost->description }}</span></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canManage)
        <form wire:submit="addCost" class="grid gap-3 text-sm sm:grid-cols-6" novalidate>
            <div class="sm:col-span-2">
                <label for="costDescription" class="block text-xs font-medium">{{ __('projects::budget.fields.description') }}</label>
                <input id="costDescription" type="text" maxlength="255" wire:model="costDescription" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="costCategory" class="block text-xs font-medium">{{ __('projects::budget.fields.category') }}</label>
                <select id="costCategory" wire:model="costCategory" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @foreach ($categories as $category)
                        <option wire:key="category-{{ $category->value }}" value="{{ $category->value }}">{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="costAmount" class="block text-xs font-medium">{{ __('projects::budget.fields.amount') }} ({{ $figures->currency ?? config('core.contracts.default_currency') }})</label>
                <input id="costAmount" type="number" step="0.01" wire:model="costAmount" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="costIncurredOn" class="block text-xs font-medium">{{ __('projects::budget.fields.incurred_on') }}</label>
                <input id="costIncurredOn" type="date" wire:model="costIncurredOn" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label for="costInvoice" class="block text-xs font-medium">{{ __('projects::budget.fields.invoice') }}</label>
                <input id="costInvoice" type="text" maxlength="100" wire:model="costInvoice" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            </div>
            <div class="sm:col-span-3">
                <label for="costSupplierId" class="block text-xs font-medium">{{ __('projects::budget.fields.supplier') }}</label>
                <select id="costSupplierId" wire:model="costSupplierId" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">—</option>
                    @foreach ($suppliers as $supplier)
                        <option wire:key="supplier-{{ $supplier->id }}" value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end sm:col-span-3">
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('projects::budget.add_cost') }}</button>
            </div>
            @foreach (['costDescription', 'costAmount', 'costIncurredOn', 'costSupplierId', 'currency'] as $field) @error($field) <p class="text-red-600 sm:col-span-6">{{ $message }}</p> @enderror @endforeach
        </form>
    @endif
</section>
