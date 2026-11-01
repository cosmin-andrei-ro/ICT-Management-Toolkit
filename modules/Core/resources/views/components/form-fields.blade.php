{{-- Fields of a Core FormSchema, bound to "<model>.<key>" of the Livewire component. --}}
@props(['fields', 'model'])

@foreach ($fields as $field)
    @php($id = $model.'-'.$field->key)
    <div wire:key="field-{{ $field->key }}">
        @if ($field->type->value === 'checkbox')
            <label class="flex items-start gap-2 text-sm">
                <input id="{{ $id }}" type="checkbox" wire:model="{{ $model }}.{{ $field->key }}" class="mt-0.5 rounded border-gray-300">
                <span>{{ $field->label }}@if ($field->required) <span class="text-red-600" aria-hidden="true">*</span>@endif</span>
            </label>
        @else
            <label for="{{ $id }}" class="block text-sm font-medium">
                {{ $field->label }}@if ($field->required) <span class="text-red-600" aria-hidden="true">*</span>@endif
            </label>

            @switch($field->type->value)
                @case('textarea')
                    <textarea id="{{ $id }}" wire:model="{{ $model }}.{{ $field->key }}" rows="4" maxlength="{{ \Modules\Core\Domain\Forms\FormField::MAX_TEXTAREA }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)></textarea>
                    @break
                @case('select')
                    <select id="{{ $id }}" wire:model="{{ $model }}.{{ $field->key }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
                        <option value="">—</option>
                        @foreach ($field->options as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @break
                @case('date')
                    <input id="{{ $id }}" type="date" wire:model="{{ $model }}.{{ $field->key }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
                    @break
                @case('number')
                    <input id="{{ $id }}" type="number" wire:model="{{ $model }}.{{ $field->key }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
                    @break
                @default
                    <input id="{{ $id }}" type="text" wire:model="{{ $model }}.{{ $field->key }}" maxlength="{{ \Modules\Core\Domain\Forms\FormField::MAX_TEXT }}" class="mt-1 block w-full rounded-md border-gray-300" @required($field->required)>
            @endswitch
        @endif

        @if ($field->help)
            <p class="mt-1 text-xs text-gray-500">{{ $field->help }}</p>
        @endif
        @error($model.'.'.$field->key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
@endforeach
