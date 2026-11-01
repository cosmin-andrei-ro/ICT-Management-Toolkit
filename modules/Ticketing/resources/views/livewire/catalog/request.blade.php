<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ route('ticketing.catalog.index') }}" class="text-sm text-gray-500 underline">{{ __('ticketing::catalog.back') }}</a>
        <h1 class="text-xl font-semibold">{{ $service->name }}</h1>
        @if ($service->description)
            <p class="whitespace-pre-line text-sm text-gray-700">{{ $service->description }}</p>
        @endif
    </header>

    <form wire:submit="submit" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        @foreach ($fields as $field)
            @php($id = 'answer-'.$field->key)
            <div wire:key="field-{{ $field->key }}">
                @if ($field->type->value === 'checkbox')
                    <label class="flex items-start gap-2 text-sm">
                        <input id="{{ $id }}" type="checkbox" wire:model="answers.{{ $field->key }}" class="mt-0.5 rounded border-gray-300">
                        <span>{{ $field->label }}@if ($field->required) <span class="text-red-600" aria-hidden="true">*</span>@endif</span>
                    </label>
                @else
                    <label for="{{ $id }}" class="block text-sm font-medium">
                        {{ $field->label }}@if ($field->required) <span class="text-red-600" aria-hidden="true">*</span>@endif
                    </label>

                    @switch($field->type->value)
                        @case('textarea')
                            <textarea id="{{ $id }}" wire:model="answers.{{ $field->key }}" rows="4" maxlength="{{ \Modules\Core\Domain\Forms\FormField::MAX_TEXTAREA }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)></textarea>
                            @break
                        @case('select')
                            <select id="{{ $id }}" wire:model="answers.{{ $field->key }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
                                <option value="">—</option>
                                @foreach ($field->options as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @break
                        @case('date')
                            <input id="{{ $id }}" type="date" wire:model="answers.{{ $field->key }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
                            @break
                        @case('number')
                            <input id="{{ $id }}" type="number" wire:model="answers.{{ $field->key }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
                            @break
                        @default
                            <input id="{{ $id }}" type="text" wire:model="answers.{{ $field->key }}" maxlength="{{ \Modules\Core\Domain\Forms\FormField::MAX_TEXT }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
                    @endswitch
                @endif

                @if ($field->help)
                    <p class="mt-1 text-xs text-gray-500">{{ $field->help }}</p>
                @endif
                @error('answers.'.$field->key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <div>
            <label for="details" class="block text-sm font-medium">{{ __('ticketing::catalog.details') }}</label>
            <textarea id="details" wire:model="details" rows="3" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
            @error('details') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="files" class="block text-sm font-medium">{{ __('ticketing::ui.fields.attachments') }}</label>
            <input id="files" type="file" wire:model="files" multiple class="mt-1 block w-full text-sm">
            @error('files.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60">{{ __('ticketing::catalog.submit') }}</button>
    </form>
</div>
