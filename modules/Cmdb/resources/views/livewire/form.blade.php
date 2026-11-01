<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $itemId ? route('cmdb.items.show', $itemId) : route('cmdb.items.index') }}" class="text-sm text-gray-500 underline">{{ __('cmdb::items.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($itemId ? 'cmdb::items.ui.edit' : 'cmdb::items.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="typeId" class="block text-sm font-medium">{{ __('cmdb::items.fields.type') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <select id="typeId" wire:model.live="typeId" class="mt-1 block w-full rounded-md border-gray-300" required>
                <option value="">—</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" wire:key="type-{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
            @error('typeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium">{{ __('cmdb::items.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="name" type="text" wire:model="name" maxlength="255" required class="mt-1 block w-full rounded-md border-gray-300">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="identifier" class="block text-sm font-medium">{{ __('cmdb::items.fields.identifier') }}</label>
                <input id="identifier" type="text" wire:model="identifier" maxlength="255" class="mt-1 block w-full rounded-md border-gray-300">
                <p class="mt-1 text-xs text-gray-500">{{ __('cmdb::items.ui.identifier_help') }}</p>
                @error('identifier') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="status" class="block text-sm font-medium">{{ __('cmdb::items.fields.status') }}</label>
                <select id="status" wire:model="status" class="mt-1 block w-full rounded-md border-gray-300">
                    @foreach ($statuses as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="environment" class="block text-sm font-medium">{{ __('cmdb::items.fields.environment') }}</label>
                <select id="environment" wire:model="environment" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">—</option>
                    @foreach ($environments as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="criticality" class="block text-sm font-medium">{{ __('cmdb::items.fields.criticality') }}</label>
                <select id="criticality" wire:model="criticality" class="mt-1 block w-full rounded-md border-gray-300">
                    @foreach ($criticalities as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('cmdb::items.fields.owner') }}</label>
                <select id="ownerId" wire:model="ownerId" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">—</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
                @error('ownerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="teamId" class="block text-sm font-medium">{{ __('cmdb::items.fields.team') }}</label>
                <select id="teamId" wire:model="teamId" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">—</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" wire:key="team-{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($fields !== [])
            <fieldset class="space-y-4 border-t border-gray-100 pt-4">
                <legend class="text-sm font-semibold">{{ __('cmdb::items.ui.attributes') }}</legend>
                <x-core::form-fields :fields="$fields" model="properties" />
            </fieldset>
        @endif

        <div>
            <label for="description" class="block text-sm font-medium">{{ __('cmdb::items.fields.description') }}</label>
            <textarea id="description" wire:model="description" rows="3" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('cmdb::items.ui.save') }}</button>
    </form>
</div>
