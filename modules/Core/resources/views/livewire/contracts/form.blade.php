<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $contractId ? route('core.contracts.show', $contractId) : route('core.contracts.index') }}" class="text-sm text-gray-500 underline">{{ __('core::contracts.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($contractId ? 'core::contracts.ui.edit' : 'core::contracts.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="title" class="block text-sm font-medium">{{ __('core::contracts.fields.title') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="title" type="text" wire:model="title" maxlength="255" required class="mt-1 block w-full">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="supplierId" class="block text-sm font-medium">{{ __('core::contracts.fields.supplier') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="supplierId" wire:model="supplierId" required class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" wire:key="supplier-{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @error('supplierId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="reference" class="block text-sm font-medium">{{ __('core::contracts.fields.reference') }}</label>
                <input id="reference" type="text" wire:model="reference" maxlength="100" class="mt-1 block w-full">
                @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="type" class="block text-sm font-medium">{{ __('core::contracts.fields.type') }}</label>
                <select id="type" wire:model="type" class="mt-1 block w-full">
                    @foreach ($types as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('core::contracts.fields.owner') }}</label>
                <select id="ownerId" wire:model="ownerId" aria-describedby="ownerId-help" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
                <p id="ownerId-help" class="mt-1 text-xs text-gray-500">{{ __('core::contracts.help.owner') }}</p>
            </div>
        </div>

        <fieldset class="space-y-4 border-t border-gray-100 pt-4">
            <legend class="text-sm font-semibold">{{ __('core::contracts.ui.terms') }}</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="startDate" class="block text-sm font-medium">{{ __('core::contracts.fields.start_date') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="startDate" type="date" wire:model="startDate" required class="mt-1 block w-full">
                    @error('startDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="endDate" class="block text-sm font-medium">{{ __('core::contracts.fields.end_date') }}</label>
                    <input id="endDate" type="date" wire:model.live="endDate" aria-describedby="endDate-help" class="mt-1 block w-full">
                    <p id="endDate-help" class="mt-1 text-xs text-gray-500">{{ __('core::contracts.help.end_date') }}</p>
                    @error('endDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @if ($endDate !== '')
                    <div>
                        <label for="noticeDays" class="block text-sm font-medium">{{ __('core::contracts.fields.notice_days') }}</label>
                        <input id="noticeDays" type="number" min="0" max="730" wire:model="noticeDays" aria-describedby="noticeDays-help" class="mt-1 block w-full">
                        <p id="noticeDays-help" class="mt-1 text-xs text-gray-500">{{ __('core::contracts.help.notice_days') }}</p>
                        @error('noticeDays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 pt-6 text-sm">
                            <input type="checkbox" wire:model.live="autoRenews" class="rounded border-gray-300">
                            {{ __('core::contracts.fields.auto_renews') }}
                        </label>
                        @if ($autoRenews)
                            <div>
                                <label for="renewalMonths" class="block text-sm font-medium">{{ __('core::contracts.fields.renewal_months') }}</label>
                                <input id="renewalMonths" type="number" min="1" max="120" wire:model="renewalMonths" aria-describedby="renewalMonths-help" class="mt-1 block w-full">
                                <p id="renewalMonths-help" class="mt-1 text-xs text-gray-500">{{ __('core::contracts.help.renewal_months') }}</p>
                                @error('renewalMonths') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                @endif
                @if ($contractId)
                    <div>
                        <label for="terminatedOn" class="block text-sm font-medium">{{ __('core::contracts.fields.terminated_on') }}</label>
                        <input id="terminatedOn" type="date" wire:model="terminatedOn" aria-describedby="terminatedOn-help" class="mt-1 block w-full">
                        <p id="terminatedOn-help" class="mt-1 text-xs text-gray-500">{{ __('core::contracts.help.terminated_on') }}</p>
                        @error('terminatedOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </fieldset>

        <fieldset class="space-y-4 border-t border-gray-100 pt-4">
            <legend class="text-sm font-semibold">{{ __('core::contracts.ui.money') }}</legend>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="value" class="block text-sm font-medium">{{ __('core::contracts.fields.value') }}</label>
                    <input id="value" type="text" inputmode="decimal" wire:model="value" maxlength="16" class="mt-1 block w-full">
                    @error('value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="currency" class="block text-sm font-medium">{{ __('core::contracts.fields.currency') }}</label>
                    <input id="currency" type="text" wire:model="currency" maxlength="3" aria-describedby="currency-help" class="mt-1 block w-full uppercase">
                    <p id="currency-help" class="mt-1 text-xs text-gray-500">{{ __('core::contracts.help.currency') }}</p>
                    @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="billingPeriod" class="block text-sm font-medium">{{ __('core::contracts.fields.billing_period') }}</label>
                    <select id="billingPeriod" wire:model="billingPeriod" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($billingPeriods as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </fieldset>

        <div>
            <label for="description" class="block text-sm font-medium">{{ __('core::contracts.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="3" maxlength="5000" class="mt-1 block w-full"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('core::contracts.ui.save') }}</button>
    </form>
</div>
