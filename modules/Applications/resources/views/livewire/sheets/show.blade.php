@php($shared = ['options' => $options, 'people' => $people, 'suppliers' => $suppliers, 'contracts' => $contracts, 'form' => $form, 'sheet' => $sheet, 'editable' => $editable, 'matrix' => $matrix, 'integrations' => $integrations, 'firewallRules' => $firewallRules, 'files' => $files, 'application' => $application, 'cmdbAvailable' => $cmdbAvailable, 'linkedItems' => $linkedItems, 'itemCandidates' => $itemCandidates, 'processesAvailable' => $processesAvailable, 'processCandidates' => $processCandidates, 'insights' => $insights])
<div class="space-y-6">
    <header class="space-y-1">
        <a href="{{ route('applications.show', $application) }}" class="text-sm text-gray-500 underline">← {{ $application->name }}</a>
        <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold">
            {{ $application->name }} <span class="text-sm font-normal text-gray-500">{{ $application->code }}</span>
            · {{ $sheet->versionLabel() ? __('applications::sheet.ui.version', ['version' => $sheet->versionLabel()]) : __($sheet->is_major ? 'applications::sheet.ui.new_major' : 'applications::sheet.ui.new_minor') }}
            <x-applications::sheet-status :status="$sheet->status" />
        </h1>
        <p class="text-sm"><a href="{{ route('applications.sheets.document', $sheet) }}" class="text-blue-700 underline">{{ __('applications::document.open') }}</a></p>
        @if ($sheet->submitter && $sheet->submitted_at && $sheet->status === \Modules\Applications\Domain\Enums\SheetStatus::Submitted)
            <p class="text-xs text-gray-500">{{ __('applications::sheet.ui.submitted_by', ['name' => $sheet->submitter->name, 'date' => $sheet->submitted_at->toDateString()]) }}</p>
        @endif
        @if ($sheet->return_comment)
            <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-900">{{ __('applications::sheet.ui.returned_by', ['name' => $sheet->returner?->name ?? '—']) }} {{ $sheet->return_comment }}</p>
        @endif
    </header>

    @error('sections') <p class="rounded-md bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

    <div class="grid gap-6 lg:grid-cols-4">
        <nav aria-label="{{ __('applications::sheet.ui.chapters') }}" class="lg:col-span-1">
            <ol class="space-y-1 rounded-lg bg-white p-2 text-sm ring-1 ring-gray-200">
                @foreach ($chapters as $number)
                    @php($missing = collect(\Modules\Applications\Domain\Sheet\SheetSchema::chapters()[$number])->contains(fn ($section) => in_array($section->key, $incomplete, true)))
                    <li wire:key="chapter-{{ $number }}">
                        <button type="button" wire:click="openChapter({{ $number }})" @class(['flex w-full items-center justify-between gap-2 rounded px-2 py-1.5 text-left', 'bg-gray-900 text-white' => $chapter === $number, 'hover:bg-gray-50' => $chapter !== $number]) @if ($chapter === $number) aria-current="step" @endif>
                            <span>{{ $number > 0 ? $number.'. ' : '' }}{{ \Modules\Applications\Domain\Sheet\SheetSchema::chapterTitle($number) }}</span>
                            @if ($missing && $sheet->status->isOpen()) <span @class(['text-xs', 'text-amber-300' => $chapter === $number, 'text-amber-700' => $chapter !== $number])>{{ __('applications::sheet.ui.to_complete') }}</span> @endif
                        </button>
                    </li>
                @endforeach
            </ol>
            <p class="mt-2 px-2 text-xs text-gray-500">{{ __('applications::sheet.ui.more_chapters') }}</p>
        </nav>

        <div class="space-y-6 lg:col-span-3">
            @foreach ($sections as $section)
                <section wire:key="section-{{ $section->key }}" aria-labelledby="section-{{ $section->key }}" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                    <h2 id="section-{{ $section->key }}" class="text-sm font-semibold">{{ trim($section->number().' '.$section->title()) }}</h2>
                    @if ($section->guidance()) <p class="text-xs text-gray-500">{{ $section->guidance() }}</p> @endif
                    @foreach ($section->fields as $field)
                        <div wire:key="field-{{ $section->key }}-{{ $field->key }}">
                            @if (in_array($field->type, [\Modules\Applications\Domain\Sheet\FieldType::DataCategories, \Modules\Applications\Domain\Sheet\FieldType::Diagram, \Modules\Applications\Domain\Sheet\FieldType::Permissions, \Modules\Applications\Domain\Sheet\FieldType::ConfigurationItems, \Modules\Applications\Domain\Sheet\FieldType::Processes, \Modules\Applications\Domain\Sheet\FieldType::Insight], true))
                                @if (! in_array($field->type, [\Modules\Applications\Domain\Sheet\FieldType::Diagram, \Modules\Applications\Domain\Sheet\FieldType::ConfigurationItems, \Modules\Applications\Domain\Sheet\FieldType::Insight], true)) <p class="text-sm font-medium">{{ $section->label($field) }}</p> @endif
                                @include('applications::sheets.'.$field->type->value, [...$shared, 'section' => $section, 'field' => $field])
                                @error('form.'.$section->key.'.'.$field->key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @elseif ($field->type === \Modules\Applications\Domain\Sheet\FieldType::Table)
                                @php($rows = $editable ? ($form[$section->key][$field->key] ?? []) : $sheet->section($section->key)[$field->key] ?? [])
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-left text-sm">
                                        <thead class="text-xs text-gray-500">
                                            <tr>
                                                @foreach ($field->columns as $column)
                                                    <th scope="col" class="py-1 pr-2 font-medium">{{ $section->label($field, $column) }}@if ($column->required) <span class="text-red-600" aria-hidden="true">*</span>@endif</th>
                                                @endforeach
                                                @if ($editable) <th scope="col" class="sr-only">{{ __('applications::sheet.ui.actions') }}</th> @endif
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 align-top">
                                            @forelse ($rows as $index => $row)
                                                <tr wire:key="row-{{ $section->key }}-{{ $field->key }}-{{ $index }}">
                                                    @foreach ($field->columns as $column)
                                                        <td class="py-1 pr-2">
                                                            @if ($editable)
                                                                @include('applications::sheets.input', [...$shared, 'section' => $section, 'table' => $field, 'field' => $column, 'path' => 'form.'.$section->key.'.'.$field->key.'.'.$index])
                                                            @else
                                                                @include('applications::sheets.value', [...$shared, 'field' => $column, 'value' => $row[$column->key] ?? null])
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    @if ($editable)
                                                        <td class="py-1"><button type="button" wire:click="removeRow('{{ $section->key }}', '{{ $field->key }}', {{ $index }})" class="text-xs text-red-700 underline">{{ __('applications::sheet.ui.remove_row') }}</button></td>
                                                    @endif
                                                </tr>
                                            @empty
                                                <tr><td colspan="{{ count($field->columns) + 1 }}" class="py-2 text-gray-500">{{ __('applications::sheet.ui.no_rows') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if ($editable)
                                    <button type="button" wire:click="addRow('{{ $section->key }}', '{{ $field->key }}')" class="rounded-md bg-white px-2 py-1 text-xs font-semibold ring-1 ring-gray-300">{{ __('applications::sheet.ui.add_row') }}</button>
                                    @if ($section->key === '15_2' && $rows === [])
                                        <button type="button" wire:click="addTemplateSteps('{{ $section->key }}', '{{ $field->key }}')" class="rounded-md bg-white px-2 py-1 text-xs font-semibold ring-1 ring-gray-300">{{ __('applications::sheet.ui.template_steps') }}</button>
                                    @endif
                                @endif
                                @error('form.'.$section->key.'.'.$field->key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @elseif ($editable)
                                <label for="{{ str_replace('.', '-', 'form.'.$section->key.'.'.$field->key) }}" class="block text-sm font-medium">{{ $section->label($field) }}@if ($field->required) <span class="text-red-600" aria-hidden="true">*</span>@endif</label>
                                <div class="mt-1">@include('applications::sheets.input', [...$shared, 'section' => $section, 'field' => $field, 'path' => 'form.'.$section->key])</div>
                            @else
                                <p class="text-xs text-gray-500">{{ $section->label($field) }}</p>
                                <div class="text-sm">@include('applications::sheets.value', [...$shared, 'field' => $field, 'value' => $sheet->section($section->key)[$field->key] ?? null])</div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endforeach

            @if ($editable)
                <section aria-labelledby="change-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                    <h2 id="change-heading" class="text-sm font-semibold">{{ __('applications::sheet.ui.change_heading') }}</h2>
                    @if ($isFirst)
                        <p class="text-xs text-gray-500">{{ __('applications::sheet.ui.first_help') }}</p>
                    @else
                        <fieldset class="space-y-1">
                            <legend class="sr-only">{{ __('applications::sheet.ui.kind') }}</legend>
                            <label class="flex items-start gap-2 text-sm"><input type="radio" wire:model="isMajor" value="0" class="mt-1"> <span>{{ __('applications::sheet.ui.minor') }} <span class="block text-xs text-gray-500">{{ __('applications::sheet.ui.minor_help') }}</span></span></label>
                            <label class="flex items-start gap-2 text-sm"><input type="radio" wire:model="isMajor" value="1" class="mt-1"> <span>{{ __('applications::sheet.ui.major') }} <span class="block text-xs text-gray-500">{{ __('applications::sheet.ui.major_help') }}</span></span></label>
                        </fieldset>
                    @endif
                    <div>
                        <label for="changeSummary" class="block text-sm font-medium">{{ __('applications::sheet.ui.change_summary') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                        <input id="changeSummary" type="text" wire:model="changeSummary" maxlength="2000" placeholder="{{ __($isFirst ? 'applications::sheet.ui.change_example_first' : 'applications::sheet.ui.change_example') }}" class="mt-1 block w-full">
                        @error('changeSummary') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="save" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('applications::sheet.ui.save') }}</button>
                        <button type="button" wire:click="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __($sheet->is_major ? 'applications::sheet.ui.submit' : 'applications::sheet.ui.publish') }}</button>
                        <span wire:loading wire:target="save,openChapter" class="text-xs text-gray-500">{{ __('applications::sheet.ui.saving') }}</span>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('applications::sheet.ui.secrets_warning') }}</p>
                </section>
            @endif

            @if ($sheet->status === \Modules\Applications\Domain\Enums\SheetStatus::Submitted)
                <section aria-labelledby="approvals-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                    <h2 id="approvals-heading" class="text-sm font-semibold">{{ __('applications::sheet.ui.approvals') }}</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach (\Modules\Applications\Domain\Enums\ApprovalRole::cases() as $role)
                            @php($given = $sheet->approvals->firstWhere('role', $role))
                            <li wire:key="approval-{{ $role->value }}" class="flex flex-wrap justify-between gap-2">
                                <span>{{ $role->label() }}</span>
                                <span @class(['text-green-700' => $given, 'text-gray-500' => ! $given])>{{ $given ? __('applications::sheet.ui.approved_by', ['name' => $given->user?->name ?? '—', 'date' => $given->approved_at->toDateString()]) : __('applications::sheet.ui.waiting') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($canDecide)
                        <div class="space-y-2 border-t border-gray-100 pt-3">
                            <fieldset class="space-y-1">
                                <legend class="text-sm font-medium">{{ __('applications::sheet.ui.approve_as') }}</legend>
                                @foreach ($myRoles as $role)
                                    <label wire:key="my-role-{{ $role->value }}" class="flex items-center gap-2 text-sm"><input type="radio" wire:model="approvalRole" value="{{ $role->value }}"> {{ $role->label() }}</label>
                                @endforeach
                                @error('approvalRole') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </fieldset>
                            <label for="comment" class="block text-sm font-medium">{{ __('applications::sheet.ui.comment') }}</label>
                            <textarea id="comment" wire:model="comment" rows="2" maxlength="5000" class="block w-full"></textarea>
                            @error('comment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="approve" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('applications::sheet.ui.approve') }}</button>
                                <button type="button" wire:click="sendBack" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('applications::sheet.ui.send_back') }}</button>
                            </div>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
</div>
