{{-- 2.1: the template's data categories with the elements processed. --}}
@php($stored = $editable ? null : ($sheet->section($section->key)[$field->key] ?? []))
<div class="overflow-x-auto">
    <table class="min-w-full text-left text-sm">
        <thead class="text-xs text-gray-500">
            <tr>
                <th scope="col" class="w-1/4 py-1 pr-4 font-medium">{{ __('applications::data.category') }}</th>
                <th scope="col" class="py-1 font-medium">{{ __('applications::data.elements_processed') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 align-top">
            @foreach (\Modules\Applications\Domain\Sheet\DataCategories::all() as $category => $elements)
                @if ($editable || ! empty($stored[$category]))
                    <tr wire:key="category-{{ $category }}">
                        <th scope="row" class="py-2 pr-4 font-medium">{{ __('applications::data.categories.'.$category) }}</th>
                        <td class="py-2">
                            @if ($editable)
                                <div class="flex flex-wrap gap-x-4 gap-y-1">
                                    @foreach ($elements as $element)
                                        <label wire:key="element-{{ $category }}-{{ $element }}" class="flex items-center gap-1.5"><input type="checkbox" wire:model="form.{{ $section->key }}.{{ $field->key }}.{{ $category }}" value="{{ $element }}"> {{ __('applications::data.elements.'.$category.'.'.$element) }}</label>
                                    @endforeach
                                </div>
                            @else
                                {{ implode(', ', array_map(fn ($element) => __('applications::data.elements.'.$category.'.'.$element), (array) $stored[$category])) }}
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
            <tr>
                <th scope="row" class="py-2 pr-4 font-medium">{{ __('applications::data.categories.other') }}</th>
                <td class="py-2">
                    @if ($editable)
                        <textarea wire:model="form.{{ $section->key }}.{{ $field->key }}_other" rows="2" maxlength="{{ $field->max }}" aria-label="{{ __('applications::data.categories.other') }}" placeholder="{{ __('applications::data.other_placeholder') }}" class="block w-full"></textarea>
                    @else
                        <span class="whitespace-pre-line">{{ $sheet->section($section->key)[$field->key.'_other'] ?? '—' }}</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
</div>
