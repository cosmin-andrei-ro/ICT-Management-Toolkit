<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ route('bcm.plans.show', $plan) }}" class="text-sm text-gray-500 underline">← {{ $plan->name }}</a>
        <h1 class="text-xl font-semibold">{{ __('bcm::exercises.ui.new') }}</h1>
        <p class="text-sm text-gray-600">{{ __('bcm::exercises.ui.new_help') }}</p>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">{{ __('bcm::exercises.fields.type') }}</legend>
            @foreach ($types as $option)
                <label wire:key="type-{{ $option->value }}" class="flex items-start gap-2 text-sm">
                    <input type="radio" wire:model="type" value="{{ $option->value }}" class="mt-1">
                    <span>{{ $option->label() }} <span class="block text-xs text-gray-500">{{ __('bcm::exercises.type_help.'.$option->value) }}</span></span>
                </label>
            @endforeach
            @error('type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </fieldset>
        <div>
            <label for="title" class="block text-sm font-medium">{{ __('bcm::exercises.fields.title') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="title" type="text" wire:model="title" maxlength="255" required class="mt-1 block w-full">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="scenario" class="block text-sm font-medium">{{ __('bcm::exercises.fields.scenario') }}</label>
                <select id="scenario" wire:model="scenario" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($scenarios as $option) <option value="{{ $option->value }}" wire:key="scenario-{{ $option->value }}">{{ __('bcm::plans.scenarios.'.$option->value) }}</option> @endforeach
                </select>
                @error('scenario') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="scheduledOn" class="block text-sm font-medium">{{ __('bcm::exercises.fields.scheduled_on') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="scheduledOn" type="date" wire:model="scheduledOn" required class="mt-1 block w-full">
                @error('scheduledOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="facilitatorId" class="block text-sm font-medium">{{ __('bcm::exercises.fields.facilitator') }}</label>
            <select id="facilitatorId" wire:model="facilitatorId" class="mt-1 block w-full">
                <option value="">—</option>
                @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="person-{{ $person->id }}">{{ $person->name }}</option> @endforeach
            </select>
            @error('facilitatorId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="participants" class="block text-sm font-medium">{{ __('bcm::exercises.fields.participants') }}</label>
            <textarea id="participants" wire:model="participants" rows="3" maxlength="5000" class="mt-1 block w-full"></textarea>
            @error('participants') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::exercises.ui.schedule') }}</button>
    </form>
</div>
