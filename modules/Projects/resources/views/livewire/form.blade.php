<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $projectId ? route('projects.show', $projectId) : route('projects.index') }}" class="text-sm text-gray-500 underline">{{ __('projects::projects.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($projectId ? 'projects::projects.edit' : 'projects::projects.new') }}</h1>
    </header>
    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="name" class="block text-sm font-medium">{{ __('projects::projects.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="name" type="text" maxlength="150" wire:model="name" required class="mt-1 block w-full rounded-md border-gray-300">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">{{ __('projects::projects.fields.description') }}</label>
            <textarea id="description" rows="4" maxlength="10000" wire:model="description" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="kind" class="block text-sm font-medium">{{ __('projects::projects.fields.kind') }}</label>
                <select id="kind" wire:model="kind" class="mt-1 block w-full rounded-md border-gray-300">
                    @foreach ($kinds as $option)
                        <option wire:key="kind-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            @foreach (['managerId' => ['manager', true], 'sponsorId' => ['sponsor', false]] as $property => [$label, $required])
                <div wire:key="person-{{ $property }}">
                    <label for="{{ $property }}" class="block text-sm font-medium">{{ __('projects::projects.fields.'.$label) }} @if ($required)<span class="text-red-600" aria-hidden="true">*</span>@endif</label>
                    <select id="{{ $property }}" wire:model="{{ $property }}" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">—</option>
                        @foreach ($people as $person)
                            <option wire:key="{{ $property }}-{{ $person->id }}" value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                    @error($property) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div>
                <label for="plannedStart" class="block text-sm font-medium">{{ __('projects::projects.fields.planned_start') }}</label>
                <input id="plannedStart" type="date" wire:model="plannedStart" class="mt-1 block w-full rounded-md border-gray-300">
            </div>
            <div>
                <label for="plannedEnd" class="block text-sm font-medium">{{ __('projects::projects.fields.planned_end') }}</label>
                <input id="plannedEnd" type="date" wire:model="plannedEnd" class="mt-1 block w-full rounded-md border-gray-300">
                @error('plannedEnd') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="text-xs text-gray-500">{{ __('projects::projects.form_help') }}</p>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('projects::projects.save') }}</button>
    </form>
</div>
