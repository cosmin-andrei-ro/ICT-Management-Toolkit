<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $spaceId ? route('documentation.spaces.show', $spaceId) : route('documentation.index') }}" class="text-sm text-gray-500 underline">{{ __('documentation::docs.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($spaceId ? 'documentation::docs.spaces.edit' : 'documentation::docs.spaces.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="name" class="block text-sm font-medium">{{ __('documentation::docs.spaces.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="name" type="text" wire:model="name" maxlength="150" placeholder="{{ __('documentation::docs.spaces.name_example') }}" class="mt-1 block w-full">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">{{ __('documentation::docs.spaces.description') }}</label>
            <textarea id="description" wire:model="description" rows="2" maxlength="2000" class="mt-1 block w-full"></textarea>
        </div>
        <fieldset>
            <legend class="text-sm font-medium">{{ __('documentation::docs.spaces.visibility') }}</legend>
            <div class="mt-1 space-y-1 text-sm">
                @foreach ($visibilities as $option)
                    <label wire:key="visibility-{{ $option->value }}" class="flex items-start gap-2"><input type="radio" wire:model.live="visibility" value="{{ $option->value }}" class="mt-1"> <span>{{ $option->label() }} <span class="block text-xs text-gray-500">{{ __('documentation::docs.visibility_help.'.$option->value) }}</span></span></label>
                @endforeach
            </div>
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="requiresApproval" class="mt-1"> <span>{{ __('documentation::docs.spaces.requires_approval') }} <span class="block text-xs text-gray-500">{{ __('documentation::docs.spaces.requires_approval_help') }}</span></span></label>
            <div>
                <label for="reviewMonths" class="block text-sm font-medium">{{ __('documentation::docs.spaces.review_months') }}</label>
                <input id="reviewMonths" type="number" min="1" max="60" wire:model="reviewMonths" class="mt-1 block w-28">
                @error('reviewMonths') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        @if ($visibility === 'teams')
            <div>
                <label for="readTeamIds" class="block text-sm font-medium">{{ __('documentation::docs.spaces.read_teams') }}</label>
                <select id="readTeamIds" wire:model="readTeamIds" multiple size="5" class="mt-1 block w-full">
                    @foreach ($teams as $team) <option value="{{ $team->id }}" wire:key="read-{{ $team->id }}">{{ $team->name }}</option> @endforeach
                </select>
                @error('readTeamIds') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif
        <div>
            <label for="editTeamIds" class="block text-sm font-medium">{{ __('documentation::docs.spaces.edit_teams') }}</label>
            <select id="editTeamIds" wire:model="editTeamIds" multiple size="5" class="mt-1 block w-full">
                @foreach ($teams as $team) <option value="{{ $team->id }}" wire:key="edit-{{ $team->id }}">{{ $team->name }}</option> @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">{{ __('documentation::docs.spaces.edit_teams_help') }}</p>
        </div>
        <div>
            <label for="ownerIds" class="block text-sm font-medium">{{ __('documentation::docs.spaces.owners') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <select id="ownerIds" wire:model="ownerIds" multiple size="6" class="mt-1 block w-full">
                @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option> @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">{{ __('documentation::docs.spaces.owners_help') }}</p>
            @error('ownerIds') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('documentation::docs.save') }}</button>
    </form>
</div>
