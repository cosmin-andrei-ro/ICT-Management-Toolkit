<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $licenseId ? route('itam.licenses.show', $licenseId) : route('itam.licenses.index') }}" class="text-sm text-gray-500 underline">{{ __('itam::licenses.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($licenseId ? 'itam::licenses.ui.edit' : 'itam::licenses.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="productId" class="block text-sm font-medium">{{ __('itam::licenses.fields.product') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="productId" wire:model="productId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" wire:key="product-{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                @error('productId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="newProduct" class="block text-sm font-medium">{{ __('itam::licenses.fields.new_product') }}</label>
                <input id="newProduct" type="text" wire:model="newProduct" maxlength="255" aria-describedby="newProduct-help" class="mt-1 block w-full">
                <p id="newProduct-help" class="mt-1 text-xs text-gray-500">{{ __('itam::licenses.help.new_product') }}</p>
            </div>
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-medium">{{ __('itam::licenses.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="name" type="text" wire:model="name" maxlength="255" required aria-describedby="name-help" class="mt-1 block w-full">
                <p id="name-help" class="mt-1 text-xs text-gray-500">{{ __('itam::licenses.help.name') }}</p>
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="model" class="block text-sm font-medium">{{ __('itam::licenses.fields.model') }}</label>
                <select id="model" wire:model.live="model" class="mt-1 block w-full">
                    @foreach ($models as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                @error('model') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            @if ($model !== 'site')
                <div>
                    <label for="quantity" class="block text-sm font-medium">{{ __('itam::licenses.fields.quantity') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="quantity" type="number" min="1" wire:model="quantity" class="mt-1 block w-full">
                    @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <label for="startsOn" class="block text-sm font-medium">{{ __('itam::licenses.fields.starts_on') }}</label>
                <input id="startsOn" type="date" wire:model="startsOn" class="mt-1 block w-full">
            </div>
            <div>
                <label for="expiresOn" class="block text-sm font-medium">{{ __('itam::licenses.fields.expires_on') }}</label>
                <input id="expiresOn" type="date" wire:model="expiresOn" aria-describedby="expiresOn-help" class="mt-1 block w-full">
                <p id="expiresOn-help" class="mt-1 text-xs text-gray-500">{{ __('itam::licenses.help.expires_on') }}</p>
                @error('expiresOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset class="space-y-2 border-t border-gray-100 pt-4">
            <legend class="text-sm font-semibold">{{ __('itam::licenses.fields.key') }}</legend>
            <input type="password" wire:model="newKey" maxlength="5000" autocomplete="off" aria-label="{{ __('itam::licenses.fields.key') }}" aria-describedby="newKey-help" class="block w-full font-mono">
            <p id="newKey-help" class="text-xs text-gray-500">{{ __('itam::licenses.help.new_key') }}</p>
            @if ($hasKey)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="removeKey" class="rounded border-gray-300"> {{ __('itam::licenses.help.remove_key') }}</label>
            @endif
        </fieldset>

        <fieldset class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
            <div>
                <label for="supplierId" class="block text-sm font-medium">{{ __('itam::licenses.fields.supplier') }}</label>
                <select id="supplierId" wire:model="supplierId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" wire:key="supplier-{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($canSeeContracts)
                <div>
                    <label for="contractId" class="block text-sm font-medium">{{ __('itam::licenses.fields.contract') }}</label>
                    <select id="contractId" wire:model="contractId" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($contracts as $contract)
                            <option value="{{ $contract->id }}" wire:key="contract-{{ $contract->id }}">{{ $contract->title }} ({{ $contract->supplier->name }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="purchaseDate" class="block text-sm font-medium">{{ __('itam::licenses.fields.purchase_date') }}</label>
                <input id="purchaseDate" type="date" wire:model="purchaseDate" class="mt-1 block w-full">
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                    <label for="cost" class="block text-sm font-medium">{{ __('itam::licenses.fields.cost') }}</label>
                    <input id="cost" type="text" inputmode="decimal" wire:model="cost" maxlength="16" class="mt-1 block w-full">
                    @error('cost') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="currency" class="block text-sm font-medium">{{ __('itam::licenses.fields.currency') }}</label>
                    <input id="currency" type="text" wire:model="currency" maxlength="3" class="mt-1 block w-full uppercase">
                    @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </fieldset>

        <div>
            <label for="notes" class="block text-sm font-medium">{{ __('itam::licenses.fields.notes') }}</label>
            <textarea id="notes" wire:model="notes" rows="3" maxlength="5000" class="mt-1 block w-full"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('itam::licenses.ui.save') }}</button>
    </form>
</div>
