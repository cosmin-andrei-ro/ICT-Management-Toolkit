{{-- One stored value of the sheet, read-only. --}}
@php($type = $field->type)
@if ($value === null || $value === '' || $value === [])
    <span class="text-gray-400">—</span>
@elseif ($type === \Modules\Applications\Domain\Sheet\FieldType::Option)
    {{ $options->label($field->list, $value) }}
@elseif ($type === \Modules\Applications\Domain\Sheet\FieldType::Choices)
    {{ implode(', ', array_map(fn ($choice) => __('applications::sheet.choices.'.$field->key.'.'.$choice), (array) $value)) }}
@elseif ($type === \Modules\Applications\Domain\Sheet\FieldType::User)
    {{ $people->firstWhere('id', $value)?->name ?? __('applications::sheet.ui.unknown') }}
@elseif ($type === \Modules\Applications\Domain\Sheet\FieldType::Supplier)
    {{ $suppliers->firstWhere('id', $value)?->name ?? __('applications::sheet.ui.unknown') }}
@elseif ($type === \Modules\Applications\Domain\Sheet\FieldType::Contract)
    {{ $contracts->firstWhere('id', $value)?->title ?? __('applications::sheet.ui.unknown') }}
@elseif ($type === \Modules\Applications\Domain\Sheet\FieldType::Url && is_string($value) && preg_match('#^https?://#i', $value))
    <a href="{{ $value }}" rel="noopener noreferrer" target="_blank" class="break-all text-blue-700 underline">{{ $value }}</a>
@elseif ($type === \Modules\Applications\Domain\Sheet\FieldType::Contacts)
    <dl class="grid gap-x-4 gap-y-1 sm:grid-cols-2">
        @foreach ((array) $value as $key => $contact)
            <div wire:key="contact-{{ $key }}"><dt class="text-xs text-gray-500">{{ $options->label($field->list, (string) $key) }}</dt><dd class="break-all">{{ $contact }}</dd></div>
        @endforeach
    </dl>
@else
    <span class="whitespace-pre-line break-words">{{ is_scalar($value) ? $value : '' }}</span>
@endif
