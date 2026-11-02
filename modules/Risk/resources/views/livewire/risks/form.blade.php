<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $riskId ? route('risk.risks.show', $riskId) : route('risk.index') }}" class="text-sm text-gray-500 underline">{{ __('risk::risks.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($riskId ? 'risk::risks.ui.edit' : 'risk::risks.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="title" class="block text-sm font-medium">{{ __('risk::risks.fields.title') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="title" type="text" wire:model="title" maxlength="255" required placeholder="{{ __('risk::risks.examples.title') }}" class="mt-1 block w-full">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">{{ __('risk::risks.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="4" maxlength="10000" placeholder="{{ __('risk::risks.examples.description') }}" class="mt-1 block w-full"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="category" class="block text-sm font-medium">{{ __('risk::risks.fields.category') }}</label>
                <select id="category" wire:model="category" class="mt-1 block w-full">
                    @foreach ($categories as $option) <option value="{{ $option->value }}" wire:key="category-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('risk::risks.fields.owner') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="ownerId" wire:model="ownerId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                </select>
                @error('ownerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium">{{ __('risk::risks.fields.status') }}</label>
                <select id="status" wire:model="status" class="mt-1 block w-full">
                    @foreach ($statuses as $option) <option value="{{ $option->value }}" wire:key="status-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                </select>
            </div>
        </div>
        <p class="text-xs text-gray-500">{{ __('risk::risks.ui.owner_help') }}</p>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('risk::risks.ui.save') }}</button>
    </form>
</div>
