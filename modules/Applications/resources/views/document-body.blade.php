{{-- The full documentation of a version, as the template lays it out. Shared by the printable page and the PDF. --}}
@php($shared = ['options' => $options, 'people' => $people, 'suppliers' => $suppliers, 'contracts' => $contracts, 'form' => $form, 'sheet' => $sheet, 'editable' => false, 'matrix' => $matrix, 'integrations' => $integrations, 'firewallRules' => $firewallRules, 'files' => $files, 'application' => $application, 'cmdbAvailable' => $cmdbAvailable, 'linkedItems' => $linkedItems, 'itemCandidates' => [], 'processesAvailable' => $processesAvailable, 'processCandidates' => [], 'insights' => $insights, 'imageSrc' => $imageSrc, 'pdf' => $pdf])
@php($doc = $sheet->section('doc'))
<main class="mx-auto max-w-4xl space-y-8 bg-white p-8 text-sm text-gray-900 print:max-w-none print:p-0">
    <header class="space-y-2 border-b border-gray-300 pb-6">
        <p class="text-gray-600">{{ $organization?->name }}</p>
        <h1 class="text-2xl font-semibold">{{ __('applications::document.title') }}</h1>
        <p class="text-lg">{{ $application->name }} <span class="text-gray-600">· {{ $application->code }}</span></p>
        @if ($sheet->status !== \Modules\Applications\Domain\Enums\SheetStatus::Approved)
            <p class="rounded border border-amber-400 bg-amber-50 p-2 font-medium text-amber-900">{{ __('applications::document.not_in_force', ['status' => $sheet->status->label()]) }}</p>
        @endif
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1">
            <dt class="text-gray-600">{{ __('applications::applications.fields.version') }}</dt><dd>{{ $sheet->versionLabel() ?? '—' }}</dd>
            <dt class="text-gray-600">{{ __('applications::applications.fields.department_owner') }}</dt><dd>{{ $application->department?->name ?? '—' }}</dd>
            <dt class="text-gray-600">{{ __('applications::applications.fields.department_email') }}</dt><dd>{{ $application->department_email ?? '—' }}</dd>
            <dt class="text-gray-600">{{ __('applications::applications.fields.business_owner') }}</dt><dd>{{ $application->businessOwner?->name ?? '—' }}</dd>
            <dt class="text-gray-600">{{ __('applications::applications.fields.technical_owner') }}</dt><dd>{{ $application->technicalOwner?->name ?? '—' }}</dd>
        </dl>
        <p class="text-xs text-gray-600">{{ __('applications::sheet.ui.secrets_warning') }}</p>
    </header>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">{{ __('applications::applications.ui.history') }}</h2>
        <table class="min-w-full border border-gray-300 text-left">
            <thead class="bg-gray-100 text-xs"><tr>
                <th class="border border-gray-300 px-2 py-1">{{ __('applications::applications.fields.version') }}</th>
                <th class="border border-gray-300 px-2 py-1">{{ __('applications::applications.fields.date') }}</th>
                <th class="border border-gray-300 px-2 py-1">{{ __('applications::applications.fields.author') }}</th>
                <th class="border border-gray-300 px-2 py-1">{{ __('applications::applications.fields.change') }}</th>
            </tr></thead>
            <tbody>
                @forelse ($history as $version)
                    <tr><td class="border border-gray-300 px-2 py-1">{{ $version->versionLabel() }}</td><td class="border border-gray-300 px-2 py-1">{{ $version->approved_at?->toDateString() }}</td><td class="border border-gray-300 px-2 py-1">{{ $version->submitter?->name ?? $version->author?->name ?? '—' }}</td><td class="border border-gray-300 px-2 py-1">{{ $version->change_summary }}</td></tr>
                @empty
                    <tr><td colspan="4" class="border border-gray-300 px-2 py-1 text-gray-500">{{ __('applications::applications.ui.no_history') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">{{ __('applications::applications.ui.document_control') }}</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1">
            <dt class="text-gray-600">{{ __('applications::sheet.fields.doc.document_owner') }}</dt><dd>{{ is_string($doc['document_owner'] ?? null) ? ($people->firstWhere('id', $doc['document_owner'])?->name ?? '—') : '—' }}</dd>
            <dt class="text-gray-600">{{ __('applications::applications.fields.last_review') }}</dt><dd>{{ $application->approved_at?->toDateString() ?? '—' }}</dd>
            <dt class="text-gray-600">{{ __('applications::applications.fields.next_review') }}</dt><dd>{{ $application->next_review_on?->toDateString() ?? '—' }}</dd>
            <dt class="text-gray-600">{{ __('applications::sheet.fields.doc.document_location') }}</dt><dd>{{ $doc['document_location'] ?? '—' }}</dd>
        </dl>
        @if ($sheet->approvals->isNotEmpty())
            <table class="min-w-full border border-gray-300 text-left">
                <thead class="bg-gray-100 text-xs"><tr>
                    <th class="border border-gray-300 px-2 py-1">{{ __('applications::document.role') }}</th>
                    <th class="border border-gray-300 px-2 py-1">{{ __('applications::document.name') }}</th>
                    <th class="border border-gray-300 px-2 py-1">{{ __('applications::document.approval_date') }}</th>
                    <th class="border border-gray-300 px-2 py-1">{{ __('applications::document.comments') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($sheet->approvals->sortBy(fn ($approval) => array_search($approval->role, \Modules\Applications\Domain\Enums\ApprovalRole::cases(), true)) as $approval)
                        <tr><td class="border border-gray-300 px-2 py-1">{{ $approval->role->label() }}</td><td class="border border-gray-300 px-2 py-1">{{ $approval->user?->name ?? '—' }}</td><td class="border border-gray-300 px-2 py-1">{{ $approval->approved_at->toDateString() }}</td><td class="border border-gray-300 px-2 py-1">{{ $approval->comment }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <nav class="space-y-1" aria-labelledby="toc-heading">
        <h2 id="toc-heading" class="text-base font-semibold">{{ __('applications::document.contents') }}</h2>
        <ol class="columns-2 text-sm">
            @foreach ($chapters as $number => $sections)
                @if ($number > 0) <li>{{ $number }}. {{ \Modules\Applications\Domain\Sheet\SheetSchema::chapterTitle($number) }}</li> @endif
            @endforeach
        </ol>
    </nav>

    @foreach ($chapters as $number => $sections)
        @continue($number === \Modules\Applications\Domain\Sheet\SheetSchema::DOCUMENT_CONTROL)
        <section class="space-y-4 break-before-page">
            <h2 class="border-b border-gray-300 pb-1 text-lg font-semibold">{{ $number }}. {{ \Modules\Applications\Domain\Sheet\SheetSchema::chapterTitle($number) }}</h2>
            @foreach ($sections as $section)
                <div class="space-y-2 break-inside-avoid-page">
                    <h3 class="font-semibold">{{ trim($section->number().' '.$section->title()) }}</h3>
                    @foreach ($section->fields as $field)
                        <div>
                            @if (in_array($field->type, [\Modules\Applications\Domain\Sheet\FieldType::DataCategories, \Modules\Applications\Domain\Sheet\FieldType::Diagram, \Modules\Applications\Domain\Sheet\FieldType::Permissions, \Modules\Applications\Domain\Sheet\FieldType::ConfigurationItems, \Modules\Applications\Domain\Sheet\FieldType::Processes, \Modules\Applications\Domain\Sheet\FieldType::Insight], true))
                                @if (! in_array($field->type, [\Modules\Applications\Domain\Sheet\FieldType::Diagram, \Modules\Applications\Domain\Sheet\FieldType::ConfigurationItems, \Modules\Applications\Domain\Sheet\FieldType::Insight], true)) <p class="text-xs text-gray-600">{{ $section->label($field) }}</p> @endif
                                @include('applications::sheets.'.$field->type->value, [...$shared, 'section' => $section, 'field' => $field])
                            @elseif ($field->type === \Modules\Applications\Domain\Sheet\FieldType::Table)
                                @php($rows = $sheet->section($section->key)[$field->key] ?? [])
                                <table class="min-w-full border border-gray-300 text-left text-xs">
                                    <thead class="bg-gray-100"><tr>
                                        @foreach ($field->columns as $column) <th class="border border-gray-300 px-1.5 py-1">{{ $section->label($field, $column) }}</th> @endforeach
                                    </tr></thead>
                                    <tbody>
                                        @forelse ((array) $rows as $row)
                                            <tr>
                                                @foreach ($field->columns as $column)
                                                    <td class="border border-gray-300 px-1.5 py-1 align-top">@include('applications::sheets.value', [...$shared, 'field' => $column, 'value' => is_array($row) ? ($row[$column->key] ?? null) : null])</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr><td colspan="{{ count($field->columns) }}" class="border border-gray-300 px-1.5 py-1 text-gray-500">{{ __('applications::sheet.ui.no_rows') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            @else
                                <p class="text-xs text-gray-600">{{ $section->label($field) }}</p>
                                <div>@include('applications::sheets.value', [...$shared, 'field' => $field, 'value' => $sheet->section($section->key)[$field->key] ?? null])</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>
    @endforeach

    <footer class="border-t border-gray-300 pt-2 text-xs text-gray-500">{{ __('applications::document.generated', ['date' => now()->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d H:i'), 'app' => config('app.name')]) }}</footer>
</main>
