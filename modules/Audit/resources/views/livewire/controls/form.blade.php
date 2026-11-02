<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $controlId ? route('audit.controls.show', $controlId) : route('audit.controls.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::compliance.controls.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($controlId ? 'audit::compliance.controls.edit' : 'audit::compliance.controls.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="name" class="block text-sm font-medium">{{ __('audit::compliance.controls.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="name" type="text" wire:model="name" maxlength="255" required placeholder="{{ __('audit::compliance.controls.examples.name') }}" class="mt-1 block w-full">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">{{ __('audit::compliance.controls.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="3" maxlength="10000" placeholder="{{ __('audit::compliance.controls.examples.description') }}" class="mt-1 block w-full"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('audit::compliance.controls.fields.owner') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="ownerId" wire:model="ownerId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                </select>
                @error('ownerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium">{{ __('audit::compliance.controls.fields.status') }}</label>
                <select id="status" wire:model="status" class="mt-1 block w-full">
                    @foreach ($statuses as $option) <option value="{{ $option->value }}" wire:key="status-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                </select>
            </div>
        </div>
        <p class="text-xs text-gray-500">{{ __('audit::compliance.controls.owner_help') }}</p>
        <div>
            <label for="notes" class="block text-sm font-medium">{{ __('audit::compliance.controls.fields.notes') }}</label>
            <textarea id="notes" wire:model="notes" rows="3" maxlength="10000" placeholder="{{ __('audit::compliance.controls.examples.notes') }}" class="mt-1 block w-full"></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="testInterval" class="block text-sm font-medium">{{ __('audit::evidence.tests.interval') }}</label>
            <input id="testInterval" type="number" min="1" max="60" wire:model="testInterval" class="mt-1 block w-32">
            <p class="mt-1 text-xs text-gray-500">{{ __('audit::evidence.tests.interval_help') }}</p>
            @error('testInterval') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::compliance.controls.save') }}</button>
    </form>
</div>
