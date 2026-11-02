{{-- 3.4: functionalities (2.3) × roles (3.3). --}}
@php($stored = collect($sheet->section($section->key)[$field->key] ?? []))
@if ($matrix['rows'] === [] || $matrix['roles'] === [])
    <p class="text-sm text-gray-500">{{ __('applications::sheet.ui.matrix_empty') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs text-gray-500">
                <tr>
                    <th scope="col" class="py-1 pr-3 font-medium">{{ __('applications::sheet.fields.2_3.functionalities.module') }}</th>
                    <th scope="col" class="py-1 pr-3 font-medium">{{ __('applications::sheet.fields.2_3.functionalities.menu') }}</th>
                    <th scope="col" class="py-1 pr-3 font-medium">{{ __('applications::sheet.fields.2_3.functionalities.functionality') }}</th>
                    @foreach ($matrix['roles'] as $roleKey => $role) <th scope="col" wire:key="role-{{ $roleKey }}" class="px-2 py-1 text-center font-medium">{{ $role }}</th> @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($matrix['rows'] as $rowKey => $row)
                    @php($granted = $stored->first(fn ($entry) => is_array($entry) && ($entry['module'] ?? '') === $row['module'] && ($entry['menu'] ?? '') === $row['menu'] && ($entry['functionality'] ?? '') === $row['functionality'])['roles'] ?? [])
                    <tr wire:key="permission-{{ $rowKey }}">
                        <td class="py-1.5 pr-3">{{ $row['module'] }}</td>
                        <td class="py-1.5 pr-3">{{ $row['menu'] }}</td>
                        <td class="py-1.5 pr-3">{{ $row['functionality'] }}</td>
                        @foreach ($matrix['roles'] as $roleKey => $role)
                            <td wire:key="cell-{{ $rowKey }}-{{ $roleKey }}" class="px-2 py-1.5 text-center">
                                @if ($editable)
                                    <input type="checkbox" wire:model="form.{{ $section->key }}.{{ $field->key }}.{{ $rowKey }}.{{ $roleKey }}" aria-label="{{ __('applications::sheet.ui.matrix_cell', ['role' => $role, 'functionality' => $row['functionality']]) }}">
                                @elseif (in_array($role, (array) $granted, true))
                                    <span aria-label="{{ __('applications::sheet.ui.granted') }}">✓</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@if ($editable) <p class="text-xs text-gray-500">{{ __('applications::sheet.ui.matrix_help') }}</p> @endif
