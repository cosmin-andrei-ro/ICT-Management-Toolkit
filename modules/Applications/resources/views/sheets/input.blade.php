{{-- One editable value of the sheet. $path is the Livewire path of its parent ("form.1_4" or "form.1_4.environments.0"). --}}
@php($id = str_replace('.', '-', $path.'.'.$field->key))
@php($label = $section->label($table ?? $field, isset($table) ? $field : null))
@php($example = $section->example($table ?? $field, isset($table) ? $field : null))
@switch($field->type)
    @case(\Modules\Applications\Domain\Sheet\FieldType::Textarea)
        <textarea id="{{ $id }}" wire:model="{{ $path }}.{{ $field->key }}" rows="4" maxlength="{{ $field->max }}" @if ($example) placeholder="{{ $example }}" @endif @if (isset($table)) aria-label="{{ $label }}" @endif class="block w-full"></textarea>
        @break
    @case(\Modules\Applications\Domain\Sheet\FieldType::Option)
        <select id="{{ $id }}" @if ($field->allowOther) wire:model.live="{{ $path }}.{{ $field->key }}" @else wire:model="{{ $path }}.{{ $field->key }}" @endif @if (isset($table)) aria-label="{{ $label }}" @endif class="block w-full">
            <option value="">{{ __('applications::sheet.ui.choose') }}</option>
            @foreach ($options->choices($field->list) as $key => $optionLabel) <option value="{{ $key }}" wire:key="{{ $id }}-{{ $key }}">{{ $optionLabel }}</option> @endforeach
            @if ($field->allowOther) <option value="{{ \Modules\Applications\Application\Sheets\SheetForm::OTHER }}">{{ __('applications::sheet.ui.other') }}</option> @endif
        </select>
        @if ($field->allowOther && data_get($form, \Illuminate\Support\Str::after($path, 'form.').'.'.$field->key) === \Modules\Applications\Application\Sheets\SheetForm::OTHER)
            <input type="text" wire:model="{{ $path }}.{{ $field->key }}_other" maxlength="255" aria-label="{{ __('applications::sheet.ui.other_text', ['field' => $label]) }}" placeholder="{{ __('applications::sheet.ui.other_placeholder') }}" class="mt-1 block w-full">
        @endif
        @break
    @case(\Modules\Applications\Domain\Sheet\FieldType::Choices)
        <div class="flex flex-wrap gap-x-4 gap-y-1">
            @foreach ($field->choices as $choice)
                <label wire:key="{{ $id }}-{{ $choice }}" class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="{{ $path }}.{{ $field->key }}" value="{{ $choice }}"> {{ __('applications::sheet.choices.'.$field->key.'.'.$choice) }}</label>
            @endforeach
        </div>
        @break
    @case(\Modules\Applications\Domain\Sheet\FieldType::User)
        <select id="{{ $id }}" wire:model="{{ $path }}.{{ $field->key }}" class="block w-full">
            <option value="">—</option>
            @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="{{ $id }}-{{ $person->id }}">{{ $person->name }}</option> @endforeach
        </select>
        @break
    @case(\Modules\Applications\Domain\Sheet\FieldType::Supplier)
        <select id="{{ $id }}" wire:model="{{ $path }}.{{ $field->key }}" class="block w-full">
            <option value="">—</option>
            @foreach ($suppliers as $supplier) <option value="{{ $supplier->id }}" wire:key="{{ $id }}-{{ $supplier->id }}">{{ $supplier->name }}</option> @endforeach
        </select>
        @break
    @case(\Modules\Applications\Domain\Sheet\FieldType::Contract)
        <select id="{{ $id }}" wire:model="{{ $path }}.{{ $field->key }}" class="block w-full">
            <option value="">—</option>
            @foreach ($contracts as $contract) <option value="{{ $contract->id }}" wire:key="{{ $id }}-{{ $contract->id }}">{{ $contract->title }}</option> @endforeach
        </select>
        @break
    @case(\Modules\Applications\Domain\Sheet\FieldType::Contacts)
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($options->choices($field->list) as $key => $team)
                <label wire:key="{{ $id }}-{{ $key }}" class="block text-sm">
                    <span class="text-xs text-gray-600">{{ $team }}</span>
                    <input type="text" wire:model="{{ $path }}.{{ $field->key }}.{{ $key }}" maxlength="255" placeholder="{{ __('applications::sheet.ui.contact_placeholder') }}" class="mt-0.5 block w-full">
                </label>
            @endforeach
        </div>
        @break
    @default
        <input id="{{ $id }}" type="{{ match ($field->type) { \Modules\Applications\Domain\Sheet\FieldType::Email => 'email', \Modules\Applications\Domain\Sheet\FieldType::Url => 'url', \Modules\Applications\Domain\Sheet\FieldType::Date => 'date', default => 'text' } }}" wire:model="{{ $path }}.{{ $field->key }}" maxlength="{{ $field->max }}" @if ($example) placeholder="{{ $example }}" @endif @if (isset($table)) aria-label="{{ $label }}" @endif class="block w-full">
@endswitch
@error($path.'.'.$field->key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
