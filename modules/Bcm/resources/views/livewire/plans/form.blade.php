<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $planId ? route('bcm.plans.show', $planId) : route('bcm.plans.index') }}" class="text-sm text-gray-500 underline">{{ __('bcm::plans.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($planId ? 'bcm::plans.ui.edit' : 'bcm::plans.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">{{ __('bcm::plans.fields.type') }}</legend>
            @foreach ($types as $option)
                <label wire:key="type-{{ $option->value }}" class="flex items-center gap-2 text-sm"><input type="radio" wire:model="type" value="{{ $option->value }}"> {{ $option->label() }}</label>
            @endforeach
            @error('type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </fieldset>
        <div>
            <label for="name" class="block text-sm font-medium">{{ __('bcm::plans.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="name" type="text" wire:model="name" maxlength="255" required class="mt-1 block w-full">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('bcm::plans.fields.owner') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="ownerId" wire:model="ownerId" required class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                </select>
                @error('ownerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="deputyId" class="block text-sm font-medium">{{ __('bcm::plans.fields.deputy') }}</label>
                <select id="deputyId" wire:model="deputyId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="deputy-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                </select>
                @error('deputyId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium">{{ __('bcm::plans.fields.status') }}</label>
                <select id="status" wire:model="status" class="mt-1 block w-full">
                    <option value="active">{{ __('bcm::plans.plan_statuses.active') }}</option>
                    <option value="retired">{{ __('bcm::plans.plan_statuses.retired') }}</option>
                </select>
            </div>
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">{{ __('bcm::plans.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="3" maxlength="5000" class="mt-1 block w-full"></textarea>
        </div>
        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::plans.ui.save') }}</button>
    </form>
</div>
