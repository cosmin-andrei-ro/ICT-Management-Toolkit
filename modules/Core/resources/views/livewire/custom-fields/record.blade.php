<div>
    @if ($fields !== [])
        <section aria-labelledby="custom-fields-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <div class="flex items-center justify-between gap-2">
                <h2 id="custom-fields-heading" class="text-sm font-semibold">{{ __('core::custom_fields.title') }}</h2>
                @if ($canChange && ! $editing)
                    <button type="button" wire:click="edit" class="rounded-md bg-white px-3 py-1 text-sm font-medium ring-1 ring-gray-300 hover:bg-gray-50">{{ __('core::custom_fields.edit') }}</button>
                @endif
            </div>

            @if ($editing)
                <form wire:submit="save" class="space-y-3">
                    <x-core::form-fields :fields="$fields" model="answers" />
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('core::custom_fields.save') }}</button>
                        <button type="button" wire:click="cancel" class="rounded-md bg-white px-3 py-1.5 text-sm font-medium ring-1 ring-gray-300">{{ __('core::custom_fields.cancel') }}</button>
                    </div>
                </form>
            @else
                <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach ($fields as $field)
                        <div wire:key="value-{{ $field->key }}">
                            <dt class="text-xs text-gray-500">{{ $field->label }}</dt>
                            <dd class="whitespace-pre-line">
                                @php($value = $values[$field->key] ?? null)
                                @if ($value === null || $value === '')
                                    <span class="text-gray-400">—</span>
                                @elseif (is_bool($value))
                                    {{ $value ? __('core::custom_fields.yes') : __('core::custom_fields.no') }}
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>
    @endif
</div>
