<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('audit.controls.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::compliance.controls.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $control->code() }} · {{ $control->name }}</h1>
            <p class="text-sm text-gray-600">{{ __('audit::compliance.controls.fields.owner') }}: {{ $control->owner?->name ?? '—' }} · <x-audit::status :status="$control->status" /></p>
            <p class="text-sm text-gray-600">
                @if ($control->test_interval_months)
                    {{ trans_choice('audit::evidence.tests.every', $control->test_interval_months, ['count' => $control->test_interval_months]) }}
                    · {{ __('audit::evidence.tests.next', ['date' => $control->next_test_on?->toDateString() ?? '—']) }}
                    @if ($control->isTestOverdue($today)) <span class="font-semibold text-red-700">{{ __('audit::evidence.tests.overdue') }}</span> @endif
                @else
                    {{ __('audit::evidence.tests.not_scheduled') }}
                @endif
            </p>
            @if ($control->description) <p class="max-w-3xl whitespace-pre-line text-sm">{{ $control->description }}</p> @endif
        </div>
        @can('update', $control)
            <div class="flex gap-2">
                <a href="{{ route('audit.controls.edit', $control) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::compliance.controls.edit') }}</a>
                <button type="button" wire:click="delete" wire:confirm="{{ __('audit::compliance.controls.delete_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('audit::compliance.controls.delete') }}</button>
            </div>
        @endcan
    </header>

    <section aria-labelledby="implementation-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="implementation-heading" class="text-sm font-semibold">{{ __('audit::compliance.controls.implementation') }}</h2>
        @can('implement', $control)
            <form wire:submit="saveImplementation" class="space-y-3" novalidate>
                <div>
                    <label for="status" class="block text-xs text-gray-600">{{ __('audit::compliance.controls.fields.status') }}</label>
                    <select id="status" wire:model="status" class="mt-1 block">
                        @foreach ($statuses as $option) <option value="{{ $option->value }}" wire:key="status-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label for="notes" class="block text-xs text-gray-600">{{ __('audit::compliance.controls.fields.notes') }}</label>
                    <textarea id="notes" wire:model="notes" rows="3" maxlength="10000" placeholder="{{ __('audit::compliance.controls.examples.notes') }}" class="mt-1 block w-full"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::compliance.controls.save') }}</button>
                    @if ($implementationSaved) <span class="text-sm text-green-700" role="status">{{ __('audit::compliance.controls.saved') }}</span> @endif
                </div>
            </form>
        @else
            <p class="whitespace-pre-line text-sm">{{ $control->implementation_notes ?? '—' }}</p>
        @endcan
    </section>

    <section aria-labelledby="mapping-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="mapping-heading" class="text-sm font-semibold">{{ __('audit::compliance.controls.mapping') }}</h2>
        @forelse ($mapped as $frameworkName => $requirements)
            <div wire:key="mapped-{{ md5($frameworkName) }}">
                <h3 class="text-xs font-medium text-gray-600">{{ $frameworkName }}</h3>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($requirements as $requirement)
                        <li wire:key="mapped-requirement-{{ $requirement->id }}" class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                            <a href="{{ route('audit.frameworks.show', ['framework' => $requirement->framework_id, 'search' => $requirement->reference]) }}" class="text-blue-700 underline"><span class="font-mono text-xs">{{ $requirement->reference }}</span> {{ $requirement->title }}</a>
                            @can('update', $control)
                                <button type="button" wire:click="unmapRequirement('{{ $requirement->id }}')" class="text-xs text-red-700 underline">{{ __('audit::compliance.controls.unmap') }}</button>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-gray-500">{{ __('audit::compliance.controls.no_mapping') }}</p>
        @endforelse

        @can('update', $control)
            <div class="space-y-2 border-t border-gray-100 pt-3">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="mapFrameworkId" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.framework') }}</label>
                        <select id="mapFrameworkId" wire:model.live="mapFrameworkId" class="mt-1 block">
                            <option value="">—</option>
                            @foreach ($frameworks as $option) <option value="{{ $option->id }}" wire:key="framework-{{ $option->id }}">{{ $option->name }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mapSearch" class="block text-xs text-gray-600">{{ __('audit::compliance.ui.search') }}</label>
                        <input id="mapSearch" type="search" wire:model.live.debounce.400ms="mapSearch" maxlength="200" class="mt-1 block w-56">
                    </div>
                </div>
                <p class="text-xs text-gray-500">{{ __('audit::compliance.controls.mapping_help') }}</p>
                @error('mapSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($mapFrameworkId !== '')
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($candidates as $candidate)
                            <li wire:key="candidate-{{ $candidate->id }}" class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                                <span><span class="font-mono text-xs">{{ $candidate->reference }}</span> {{ $candidate->title }}</span>
                                <button type="button" wire:click="mapRequirement('{{ $candidate->id }}')" class="rounded-md bg-white px-2 py-1 text-xs font-semibold ring-1 ring-gray-300">{{ __('audit::compliance.controls.map') }}</button>
                            </li>
                        @empty
                            <li class="py-1.5 text-gray-500">{{ __('audit::compliance.controls.no_candidates') }}</li>
                        @endforelse
                    </ul>
                @endif
            </div>
        @endcan
    </section>

    <section aria-labelledby="evidence-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="evidence-heading" class="text-sm font-semibold">{{ __('audit::evidence.title') }}</h2>
        <p class="text-xs text-gray-500">{{ __('audit::evidence.intro') }}</p>
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($evidence as $item)
                <li wire:key="evidence-{{ $item->id }}" class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <div class="space-y-0.5">
                        @if ($item->kind === \Modules\Audit\Domain\Enums\EvidenceKind::Link)
                            @php($record = $resolved[$item->linked_type.':'.$item->linked_id] ?? null)
                            <p>
                                <span class="text-xs text-gray-500">{{ $kindLabels[$item->linked_type] ?? $item->linked_type }}:</span>
                                @if ($record)
                                    <a href="{{ $record->url }}" class="text-blue-700 underline">{{ $record->title }}</a>
                                    @if ($record->status) <span class="text-xs text-gray-500">({{ $record->status }})</span> @endif
                                @else
                                    {{ $item->title }} <span class="text-xs text-gray-500">{{ __('audit::evidence.not_visible') }}</span>
                                @endif
                            </p>
                        @else
                            <p>
                                <span class="text-xs text-gray-500">{{ __('audit::evidence.document') }}:</span>
                                {{ $item->title }}
                                @foreach ($item->attachments as $attachment)
                                    <a href="{{ route('core.attachments.download', $attachment) }}" wire:key="evidence-{{ $item->id }}-file-{{ $attachment->id }}" class="text-blue-700 underline">{{ $attachment->original_name }}</a>
                                @endforeach
                            </p>
                        @endif
                        @if ($item->description) <p class="whitespace-pre-line text-xs text-gray-600">{{ $item->description }}</p> @endif
                        <p class="text-xs text-gray-500">
                            {{ __('audit::evidence.added', ['name' => $item->creator?->name ?? '—', 'date' => $item->created_at?->timezone($timezone)->format('Y-m-d')]) }}
                            @if ($item->test_id) · {{ __('audit::evidence.from_test') }} @endif
                            @if ($item->valid_until)
                                · <span @class(['font-semibold text-red-700' => $item->isExpired($today)])>{{ __($item->isExpired($today) ? 'audit::evidence.expired' : 'audit::evidence.valid_until', ['date' => $item->valid_until->toDateString()]) }}</span>
                            @endif
                        </p>
                    </div>
                    @if ($canImplement)
                        <button type="button" wire:click="removeEvidence('{{ $item->id }}')" wire:confirm="{{ __('audit::evidence.remove_confirm') }}" class="text-xs text-red-700 underline">{{ __('audit::evidence.remove') }}</button>
                    @endif
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('audit::evidence.none') }}</li>
            @endforelse
        </ul>

        @if ($canImplement)
            <div class="grid gap-6 border-t border-gray-100 pt-3 md:grid-cols-2">
                <div class="space-y-2">
                    <h3 class="text-sm font-medium">{{ __('audit::evidence.link_title') }}</h3>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="evidenceType" class="block text-xs text-gray-600">{{ __('audit::evidence.kind') }}</label>
                            <select id="evidenceType" wire:model.live="evidenceType" class="mt-1 block">
                                <option value="">—</option>
                                @foreach ($kinds as $type => $label) <option value="{{ $type }}" wire:key="kind-{{ $type }}">{{ $label }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="evidenceSearch" class="block text-xs text-gray-600">{{ __('audit::compliance.ui.search') }}</label>
                            <input id="evidenceSearch" type="search" wire:model.live.debounce.400ms="evidenceSearch" maxlength="200" class="mt-1 block w-56">
                        </div>
                    </div>
                    <div>
                        <label for="evidenceNote" class="block text-xs text-gray-600">{{ __('audit::evidence.note') }}</label>
                        <input id="evidenceNote" type="text" wire:model="evidenceNote" maxlength="2000" placeholder="{{ __('audit::evidence.note_example') }}" class="mt-1 block w-full">
                    </div>
                    @error('evidenceSearch') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('evidenceType') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @if ($evidenceType !== '')
                        <ul class="divide-y divide-gray-100 text-sm">
                            @forelse ($found as $record)
                                <li wire:key="found-{{ $record->id }}" class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                                    <span>{{ $record->title }} @if ($record->status) <span class="text-xs text-gray-500">({{ $record->status }})</span> @endif</span>
                                    <button type="button" wire:click="linkEvidence('{{ $record->id }}')" class="rounded-md bg-white px-2 py-1 text-xs font-semibold ring-1 ring-gray-300">{{ __('audit::evidence.link') }}</button>
                                </li>
                            @empty
                                <li class="py-1.5 text-gray-500">{{ __('audit::evidence.nothing_found') }}</li>
                            @endforelse
                        </ul>
                    @endif
                </div>
                <form wire:submit="uploadEvidence" class="space-y-2" novalidate>
                    <h3 class="text-sm font-medium">{{ __('audit::evidence.upload_title') }}</h3>
                    <input type="file" wire:model="document" class="block w-full text-sm" aria-label="{{ __('audit::evidence.document') }}">
                    @error('document') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="documentTitle" class="block text-xs text-gray-600">{{ __('audit::evidence.document_title') }}</label>
                            <input id="documentTitle" type="text" wire:model="documentTitle" maxlength="255" placeholder="{{ __('audit::evidence.document_title_example') }}" class="mt-1 block w-full">
                        </div>
                        <div>
                            <label for="documentValidUntil" class="block text-xs text-gray-600">{{ __('audit::evidence.valid_until_field') }}</label>
                            <input id="documentValidUntil" type="date" wire:model="documentValidUntil" class="mt-1 block w-full">
                            @error('documentValidUntil') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="documentDescription" class="block text-xs text-gray-600">{{ __('audit::evidence.note') }}</label>
                        <input id="documentDescription" type="text" wire:model="documentDescription" maxlength="2000" class="mt-1 block w-full">
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::evidence.upload') }}</button>
                </form>
            </div>
        @endif
    </section>

    <section aria-labelledby="tests-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="tests-heading" class="text-sm font-semibold">{{ __('audit::evidence.tests.title') }}</h2>
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($tests as $test)
                <li wire:key="test-{{ $test->id }}" class="space-y-0.5 py-2">
                    <p>
                        <span class="font-medium">{{ $test->tested_on->toDateString() }}</span>
                        · <span @class(['font-semibold', 'text-green-700' => $test->result === \Modules\Audit\Domain\Enums\TestResult::Effective, 'text-orange-700' => $test->result === \Modules\Audit\Domain\Enums\TestResult::PartiallyEffective, 'text-red-700' => $test->result === \Modules\Audit\Domain\Enums\TestResult::Ineffective])>{{ $test->result->label() }}</span>
                        · {{ $test->tester?->name ?? '—' }}
                    </p>
                    <p class="whitespace-pre-line text-xs text-gray-600"><span class="font-medium">{{ __('audit::evidence.tests.method') }}:</span> {{ $test->method }}</p>
                    @if ($test->notes) <p class="whitespace-pre-line text-xs text-gray-600"><span class="font-medium">{{ __('audit::evidence.tests.notes') }}:</span> {{ $test->notes }}</p> @endif
                    @if ($test->result !== \Modules\Audit\Domain\Enums\TestResult::Effective && auth()->user()?->can('create', \Modules\Audit\Domain\Models\Finding::class))
                        <a href="{{ route('audit.findings.create', ['test' => $test->id]) }}" class="text-xs text-blue-700 underline">{{ __('audit::audits.findings.raise_from_test') }}</a>
                    @endif
                    @foreach ($test->evidence as $item)
                        @foreach ($item->attachments as $attachment)
                            <a href="{{ route('core.attachments.download', $attachment) }}" wire:key="test-{{ $test->id }}-file-{{ $attachment->id }}" class="text-xs text-blue-700 underline">{{ $attachment->original_name }}</a>
                        @endforeach
                    @endforeach
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('audit::evidence.tests.none') }}</li>
            @endforelse
        </ul>

        @if ($canImplement)
            <form wire:submit="recordTest" class="space-y-3 border-t border-gray-100 pt-3" novalidate>
                <h3 class="text-sm font-medium">{{ __('audit::evidence.tests.record') }}</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="testedOn" class="block text-xs text-gray-600">{{ __('audit::evidence.tests.tested_on') }}</label>
                        <input id="testedOn" type="date" wire:model="testedOn" max="{{ $today->toDateString() }}" class="mt-1 block w-full">
                        @error('testedOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="testResult" class="block text-xs text-gray-600">{{ __('audit::evidence.tests.result') }}</label>
                        <select id="testResult" wire:model="testResult" class="mt-1 block w-full">
                            @foreach ($results as $option) <option value="{{ $option->value }}" wire:key="result-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="testMethod" class="block text-xs text-gray-600">{{ __('audit::evidence.tests.method') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <textarea id="testMethod" wire:model="testMethod" rows="2" maxlength="5000" placeholder="{{ __('audit::evidence.tests.method_example') }}" class="mt-1 block w-full"></textarea>
                    @error('testMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="testNotes" class="block text-xs text-gray-600">{{ __('audit::evidence.tests.notes') }}</label>
                    <textarea id="testNotes" wire:model="testNotes" rows="2" maxlength="10000" placeholder="{{ __('audit::evidence.tests.notes_example') }}" class="mt-1 block w-full"></textarea>
                </div>
                <div>
                    <label for="testDocument" class="block text-xs text-gray-600">{{ __('audit::evidence.tests.document') }}</label>
                    <input id="testDocument" type="file" wire:model="testDocument" class="mt-1 block w-full text-sm">
                    @error('testDocument') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::evidence.tests.save') }}</button>
            </form>
        @endif
    </section>
</div>
