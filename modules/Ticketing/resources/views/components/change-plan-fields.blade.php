@props(['risks', 'disabled' => false, 'planDisabled' => false])

<div class="grid gap-4 sm:grid-cols-3">
    <label class="block text-sm">
        <span class="font-medium">{{ __('ticketing::changes.ui.risk') }}</span>
        <select wire:model="risk" class="mt-1 block w-full rounded-md border-gray-300" @disabled($disabled || $planDisabled)>
            @foreach ($risks as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">
        <span class="font-medium">{{ __('ticketing::changes.ui.window_start') }}</span>
        <input type="datetime-local" wire:model="plannedStart" class="mt-1 block w-full rounded-md border-gray-300" @disabled($disabled)>
        @error('plannedStart') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </label>
    <label class="block text-sm">
        <span class="font-medium">{{ __('ticketing::changes.ui.window_end') }}</span>
        <input type="datetime-local" wire:model="plannedEnd" class="mt-1 block w-full rounded-md border-gray-300" @disabled($disabled)>
        @error('plannedEnd') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </label>
</div>
<p class="text-xs text-gray-500">{{ __('ticketing::changes.ui.window_help', ['timezone' => config('app.display_timezone')]) }}</p>

@foreach (['implementationPlan' => 'implementation_plan', 'rollbackPlan' => 'rollback_plan', 'testPlan' => 'test_plan'] as $field => $label)
    <label class="block text-sm">
        <span class="font-medium">{{ __('ticketing::changes.ui.'.$label) }}</span>
        <textarea wire:model="{{ $field }}" rows="4" class="mt-1 block w-full rounded-md border-gray-300" @disabled($disabled || $planDisabled)></textarea>
        @error($field) <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </label>
@endforeach
