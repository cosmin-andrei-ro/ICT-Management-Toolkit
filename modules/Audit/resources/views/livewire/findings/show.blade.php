<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ $finding->audit ? route('audit.audits.show', $finding->audit) : route('audit.findings.index') }}" class="text-sm text-gray-500 underline">{{ $finding->audit ? $finding->audit->code().' · '.$finding->audit->title : __('audit::audits.findings.title') }}</a>
            <h1 class="text-xl font-semibold">{{ $finding->code() }} · {{ $finding->title }}</h1>
            <p class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <x-audit::severity :severity="$finding->severity" />
                <span class="font-semibold">{{ $finding->status->label() }}</span>
                · {{ __('audit::audits.findings.fields.owner') }}: {{ $finding->owner?->name ?? '—' }}
                @if ($finding->due_on) · <span @class(['font-semibold text-red-700' => $finding->isOverdue($today)])>{{ __('audit::audits.findings.due', ['date' => $finding->due_on->toDateString()]) }}</span> @endif
                @if ($finding->test_id) · {{ __('audit::audits.findings.from_test') }} @endif
            </p>
            @if ($finding->control) <p class="text-sm">{{ __('audit::audits.findings.fields.control') }}: <a href="{{ route('audit.controls.show', $finding->control) }}" class="text-blue-700 underline">{{ $finding->control->code() }} · {{ $finding->control->name }}</a></p> @endif
            @if ($finding->requirement) <p class="text-sm">{{ __('audit::audits.findings.fields.requirement') }}: {{ $finding->requirement->framework->name }} <span class="font-mono text-xs">{{ $finding->requirement->reference }}</span> {{ $finding->requirement->title }}</p> @endif
            <p class="max-w-3xl whitespace-pre-line text-sm">{{ $finding->description }}</p>
        </div>
        @can('update', $finding)
            <a href="{{ route('audit.findings.edit', $finding) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::audits.findings.edit') }}</a>
        @endcan
    </header>

    @if ($finding->verification_notes && $finding->status !== \Modules\Audit\Domain\Enums\FindingStatus::Closed)
        <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="status">{{ __('audit::audits.findings.reopened_because') }} {{ $finding->verification_notes }}</p>
    @endif

    <section aria-labelledby="cause-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="cause-heading" class="text-sm font-semibold">{{ __('audit::audits.findings.fields.root_cause') }}</h2>
        @if ($canWork)
            <form wire:submit="saveRootCause" class="space-y-2" novalidate>
                <label for="rootCause" class="sr-only">{{ __('audit::audits.findings.fields.root_cause') }}</label>
                <textarea id="rootCause" wire:model="rootCause" rows="3" maxlength="10000" placeholder="{{ __('audit::audits.examples.root_cause') }}" class="block w-full"></textarea>
                @error('rootCause') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::audits.ui.save') }}</button>
            </form>
        @else
            <p class="whitespace-pre-line text-sm">{{ $finding->root_cause ?? '—' }}</p>
        @endif
    </section>

    <section aria-labelledby="actions-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="actions-heading" class="text-sm font-semibold">{{ __('audit::audits.findings.actions') }}</h2>
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($actions as $action)
                <li wire:key="action-{{ $action->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div>
                        <p>{{ $action->title }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $action->owner?->name ?? '—' }}
                            @if ($action->due_on) · <span @class(['font-semibold text-red-700' => $action->isOverdue($today)])>{{ __('audit::audits.findings.due', ['date' => $action->due_on->toDateString()]) }}</span> @endif
                            · {{ $action->status->label() }}
                            @if ($action->ticket_number) · {{ $action->ticket_number }} @endif
                        </p>
                    </div>
                    @can('progressAction', [$finding, $action])
                        <div class="flex flex-wrap gap-2 text-xs">
                            @foreach ($statuses as $option)
                                @if ($option !== $action->status)
                                    <button type="button" wire:click="setActionStatus('{{ $action->id }}', '{{ $option->value }}')" wire:key="action-{{ $action->id }}-{{ $option->value }}" class="rounded-md bg-white px-2 py-1 font-semibold ring-1 ring-gray-300">{{ $option->label() }}</button>
                                @endif
                            @endforeach
                            @if ($ticketingAvailable && ! $action->ticket_id)
                                <button type="button" wire:click="raiseTicket('{{ $action->id }}')" class="rounded-md bg-white px-2 py-1 font-semibold ring-1 ring-gray-300">{{ __('audit::audits.findings.raise_ticket') }}</button>
                            @endif
                        </div>
                    @endcan
                </li>
            @empty
                <li class="py-2 text-gray-500">{{ __('audit::audits.findings.no_actions') }}</li>
            @endforelse
        </ul>

        @if ($canWork)
            <form wire:submit="addAction" class="grid gap-3 border-t border-gray-100 pt-3 sm:grid-cols-4" novalidate>
                <div class="sm:col-span-2">
                    <label for="actionTitle" class="block text-xs text-gray-600">{{ __('audit::audits.findings.action_title') }}</label>
                    <input id="actionTitle" type="text" wire:model="actionTitle" maxlength="255" placeholder="{{ __('audit::audits.examples.action') }}" class="mt-1 block w-full">
                    @error('actionTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="actionOwnerId" class="block text-xs text-gray-600">{{ __('audit::audits.findings.fields.owner') }}</label>
                    <select id="actionOwnerId" wire:model="actionOwnerId" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="action-owner-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                    </select>
                    @error('actionOwnerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="actionDueOn" class="block text-xs text-gray-600">{{ __('audit::audits.findings.fields.due_on') }}</label>
                    <input id="actionDueOn" type="date" wire:model="actionDueOn" class="mt-1 block w-full">
                </div>
                <div class="sm:col-span-4">
                    <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::audits.findings.add_action') }}</button>
                </div>
            </form>
        @endif
    </section>

    <section aria-labelledby="closure-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="closure-heading" class="text-sm font-semibold">{{ __('audit::audits.findings.closure') }}</h2>
        @if ($finding->status === \Modules\Audit\Domain\Enums\FindingStatus::Closed)
            <p class="text-sm">{{ __('audit::audits.findings.closed_by', ['name' => $finding->verifier?->name ?? '—', 'date' => $finding->closed_at?->timezone(config()->string('app.display_timezone'))->format('Y-m-d')]) }}</p>
            @if ($finding->verification_notes) <p class="whitespace-pre-line text-sm text-gray-600">{{ $finding->verification_notes }}</p> @endif
        @elseif ($finding->status === \Modules\Audit\Domain\Enums\FindingStatus::Verification)
            <p class="text-sm">{{ __('audit::audits.findings.waiting_verification') }}</p>
            @can('verify', $finding)
                <div class="space-y-2">
                    <label for="verificationNotes" class="block text-xs text-gray-600">{{ __('audit::audits.findings.verification_notes') }}</label>
                    <textarea id="verificationNotes" wire:model="verificationNotes" rows="2" maxlength="10000" placeholder="{{ __('audit::audits.examples.verification') }}" class="block w-full"></textarea>
                    @error('verificationNotes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex gap-2">
                        <button type="button" wire:click="verify(true)" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::audits.findings.close') }}</button>
                        <button type="button" wire:click="verify(false)" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('audit::audits.findings.reopen') }}</button>
                    </div>
                </div>
            @endcan
        @else
            <p class="text-sm text-gray-600">{{ __($finding->severity->isNonconformity() ? 'audit::audits.findings.submit_help_nonconformity' : 'audit::audits.findings.submit_help') }}</p>
            @if ($canWork)
                <button type="button" wire:click="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::audits.findings.submit') }}</button>
            @endif
        @endif
    </section>

    <x-core::record-history :record="$finding" :can-change="auth()->user()->can('update', $finding)" />
</div>
