<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $supplierId ? route('core.suppliers.show', $supplierId) : route('core.suppliers.index') }}" class="text-sm text-gray-500 underline">{{ __('core::suppliers.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($supplierId ? 'core::suppliers.ui.edit' : 'core::suppliers.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'name' => ['name', 255, true],
                'legalName' => ['legal_name', 255, false],
                'taxId' => ['tax_id', 64, false],
                'customerNumber' => ['customer_number', 64, false],
                'website' => ['website', 255, false],
                'supportEmail' => ['support_email', 255, false],
                'supportPhone' => ['support_phone', 64, false],
                'supportHours' => ['support_hours', 255, false],
            ] as $property => [$label, $max, $required])
                <div wire:key="field-{{ $property }}">
                    <label for="{{ $property }}" class="block text-sm font-medium">{{ __('core::suppliers.fields.'.$label) }} @if ($required)<span class="text-red-600" aria-hidden="true">*</span>@endif</label>
                    <input id="{{ $property }}" type="{{ $property === 'supportEmail' ? 'email' : ($property === 'website' ? 'url' : 'text') }}" wire:model="{{ $property }}" maxlength="{{ $max }}" @required($required) class="mt-1 block w-full rounded-md border-gray-300">
                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium">{{ __('core::suppliers.fields.notes') }}</label>
            <textarea id="notes" wire:model="notes" rows="3" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="isActive" class="rounded border-gray-300">
            {{ __('core::suppliers.fields.active') }}
        </label>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('core::suppliers.ui.save') }}</button>
    </form>
</div>
