<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ route('bcm.plans.show', $plan) }}" class="text-sm text-gray-500 underline">← {{ $plan->name }}</a>
        <h1 class="text-xl font-semibold">{{ __('bcm::activations.ui.new') }}</h1>
        <p class="text-sm text-gray-600">{{ __('bcm::activations.ui.new_help') }}</p>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="activatedAt" class="block text-sm font-medium">{{ __('bcm::activations.fields.activated_at') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="activatedAt" type="datetime-local" wire:model="activatedAt" required class="mt-1 block w-full">
                @error('activatedAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="scenario" class="block text-sm font-medium">{{ __('bcm::exercises.fields.scenario') }}</label>
                <select id="scenario" wire:model="scenario" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($scenarios as $option) <option value="{{ $option->value }}" wire:key="scenario-{{ $option->value }}">{{ __('bcm::plans.scenarios.'.$option->value) }}</option> @endforeach
                </select>
            </div>
        </div>
        <div>
            <label for="reason" class="block text-sm font-medium">{{ __('bcm::activations.fields.reason') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <textarea id="reason" wire:model="reason" rows="4" maxlength="5000" required class="mt-1 block w-full"></textarea>
            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="notifyTeam" class="mt-1"> <span>{{ __('bcm::activations.fields.notify_team') }}</span></label>
        <button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white">{{ __('bcm::activations.ui.record') }}</button>
    </form>
</div>
