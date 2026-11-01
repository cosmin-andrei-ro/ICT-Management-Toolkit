<div class="mx-auto max-w-3xl space-y-6">
    <header class="space-y-1">
        <a href="{{ route('ticketing.changes.calendar') }}" class="text-sm text-gray-500 underline">{{ __('ticketing::changes.ui.back_to_calendar') }}</a>
        <h1 class="text-xl font-semibold">{{ __('ticketing::changes.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200">
        <fieldset>
            <legend class="text-sm font-medium">{{ __('ticketing::changes.ui.type') }}</legend>
            <div class="mt-2 space-y-1">
                @foreach ($types as $option)
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" wire:model.live="type" value="{{ $option->value }}" class="mt-1">
                        <span><span class="font-medium">{{ $option->label() }}</span> — <span class="text-gray-600">{{ __('ticketing::changes.type_help.'.$option->value) }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </fieldset>

        @if ($type === 'standard')
            <label class="block text-sm">
                <span class="font-medium">{{ __('ticketing::changes.ui.template') }}</span>
                <select wire:model="templateId" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">—</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                @error('templateId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
        @else
            <label class="block text-sm">
                <span class="font-medium">{{ __('ticketing::ui.fields.title') }}</span>
                <input type="text" wire:model="title" maxlength="200" class="mt-1 block w-full rounded-md border-gray-300">
                @error('title') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
        @endif

        <label class="block text-sm">
            <span class="font-medium">{{ __('ticketing::changes.ui.reason') }}</span>
            <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
            @error('description') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </label>

        <x-ticketing::change-plan-fields :risks="$risks" :plan-disabled="$type === 'standard'" />

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('ticketing::changes.ui.create') }}</button>
    </form>
</div>
