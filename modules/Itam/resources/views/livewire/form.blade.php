<div class="mx-auto max-w-3xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $assetId ? route('itam.assets.show', $assetId) : route('itam.assets.index') }}" class="text-sm text-gray-500 underline">{{ __('itam::assets.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($assetId ? 'itam::assets.ui.edit' : 'itam::assets.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="categoryId" class="block text-sm font-medium">{{ __('itam::assets.fields.category') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="categoryId" wire:model.live="categoryId" required class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" wire:key="category-{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('categoryId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="assetTag" class="block text-sm font-medium">{{ __('itam::assets.fields.asset_tag') }}</label>
                <input id="assetTag" type="text" wire:model="assetTag" maxlength="64" aria-describedby="assetTag-help" class="mt-1 block w-full font-mono">
                <p id="assetTag-help" class="mt-1 text-xs text-gray-500">{{ __('itam::assets.help.asset_tag') }}</p>
                @error('assetTag') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="manufacturer" class="block text-sm font-medium">{{ __('itam::assets.fields.manufacturer') }}</label>
                <input id="manufacturer" type="text" wire:model="manufacturer" maxlength="255" class="mt-1 block w-full">
            </div>
            <div>
                <label for="model" class="block text-sm font-medium">{{ __('itam::assets.fields.model') }}</label>
                <input id="model" type="text" wire:model="model" maxlength="255" class="mt-1 block w-full">
            </div>
            <div>
                <label for="serialNumber" class="block text-sm font-medium">{{ __('itam::assets.fields.serial_number') }}</label>
                <input id="serialNumber" type="text" wire:model="serialNumber" maxlength="255" class="mt-1 block w-full font-mono">
                @error('serialNumber') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="name" class="block text-sm font-medium">{{ __('itam::assets.fields.name') }}</label>
                <input id="name" type="text" wire:model="name" maxlength="255" aria-describedby="name-help" class="mt-1 block w-full">
                <p id="name-help" class="mt-1 text-xs text-gray-500">{{ __('itam::assets.help.name') }}</p>
            </div>
            @unless ($assetId)
                <div>
                    <label for="status" class="block text-sm font-medium">{{ __('itam::assets.fields.status') }}</label>
                    <select id="status" wire:model="status" aria-describedby="status-help" class="mt-1 block w-full">
                        <option value="in_stock">{{ __('itam::assets.statuses.in_stock') }}</option>
                        <option value="ordered">{{ __('itam::assets.statuses.ordered') }}</option>
                    </select>
                    <p id="status-help" class="mt-1 text-xs text-gray-500">{{ __('itam::assets.help.status') }}</p>
                </div>
            @endunless
            <div>
                <label for="locationId" class="block text-sm font-medium">{{ __('itam::assets.fields.location') }}</label>
                <select id="locationId" wire:model="locationId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($locations as $id => $path)
                        <option value="{{ $id }}" wire:key="location-{{ $id }}">{{ $path }}</option>
                    @endforeach
                </select>
                @error('locationId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($fields !== [])
            <fieldset class="space-y-4 border-t border-gray-100 pt-4">
                <legend class="text-sm font-semibold">{{ __('itam::assets.ui.attributes') }}</legend>
                <x-core::form-fields :fields="$fields" model="properties" />
            </fieldset>
        @endif

        <fieldset class="space-y-4 border-t border-gray-100 pt-4">
            <legend class="text-sm font-semibold">{{ __('itam::assets.ui.purchase') }}</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="supplierId" class="block text-sm font-medium">{{ __('itam::assets.fields.supplier') }}</label>
                    <select id="supplierId" wire:model="supplierId" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" wire:key="supplier-{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canSeeContracts)
                    <div>
                        <label for="contractId" class="block text-sm font-medium">{{ __('itam::assets.fields.contract') }}</label>
                        <select id="contractId" wire:model="contractId" class="mt-1 block w-full">
                            <option value="">—</option>
                            @foreach ($contracts as $contract)
                                <option value="{{ $contract->id }}" wire:key="contract-{{ $contract->id }}">{{ $contract->title }} ({{ $contract->supplier->name }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="purchaseDate" class="block text-sm font-medium">{{ __('itam::assets.fields.purchase_date') }}</label>
                    <input id="purchaseDate" type="date" wire:model="purchaseDate" class="mt-1 block w-full">
                    @error('purchaseDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-2">
                        <label for="purchaseCost" class="block text-sm font-medium">{{ __('itam::assets.fields.purchase_cost') }}</label>
                        <input id="purchaseCost" type="text" inputmode="decimal" wire:model="purchaseCost" maxlength="16" class="mt-1 block w-full">
                        @error('purchaseCost') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium">{{ __('itam::assets.fields.currency') }}</label>
                        <input id="currency" type="text" wire:model="currency" maxlength="3" class="mt-1 block w-full uppercase">
                        @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="orderNumber" class="block text-sm font-medium">{{ __('itam::assets.fields.order_number') }}</label>
                    <input id="orderNumber" type="text" wire:model="orderNumber" maxlength="100" class="mt-1 block w-full">
                </div>
                <div>
                    <label for="invoiceNumber" class="block text-sm font-medium">{{ __('itam::assets.fields.invoice_number') }}</label>
                    <input id="invoiceNumber" type="text" wire:model="invoiceNumber" maxlength="100" class="mt-1 block w-full">
                </div>
                <div>
                    <label for="warrantyEndsOn" class="block text-sm font-medium">{{ __('itam::assets.fields.warranty_ends_on') }}</label>
                    <input id="warrantyEndsOn" type="date" wire:model="warrantyEndsOn" class="mt-1 block w-full">
                    @error('warrantyEndsOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="replaceOn" class="block text-sm font-medium">{{ __('itam::assets.fields.replace_on') }}</label>
                    <input id="replaceOn" type="date" wire:model="replaceOn" aria-describedby="replaceOn-help" class="mt-1 block w-full">
                    <p id="replaceOn-help" class="mt-1 text-xs text-gray-500">{{ __('itam::assets.help.replace_on') }}</p>
                </div>
            </div>
        </fieldset>

        <div>
            <label for="notes" class="block text-sm font-medium">{{ __('itam::assets.fields.notes') }}</label>
            <textarea id="notes" wire:model="notes" rows="3" maxlength="5000" class="mt-1 block w-full"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('itam::assets.ui.save') }}</button>
    </form>
</div>
