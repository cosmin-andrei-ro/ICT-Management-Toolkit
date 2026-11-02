<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('audit.audits.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::audits.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $audit->code() }} · {{ $audit->title }}</h1>
            <p class="text-sm text-gray-600">
                {{ $audit->kind->label() }}@if ($audit->audit_body) ({{ $audit->audit_body }})@endif
                · {{ $audit->starts_on->toDateString() }}@if ($audit->ends_on && ! $audit->ends_on->equalTo($audit->starts_on)) – {{ $audit->ends_on->toDateString() }}@endif
                · {{ __('audit::audits.fields.lead_auditor') }}: {{ $audit->leadAuditor?->name ?? '—' }}
                · <span class="font-semibold">{{ $audit->status->label() }}</span>
            </p>
            @if ($audit->frameworks->isNotEmpty()) <p class="text-sm text-gray-600">{{ __('audit::audits.fields.frameworks') }}: {{ $audit->frameworks->pluck('name')->join(', ') }}</p> @endif
            @if ($audit->scope) <p class="max-w-3xl whitespace-pre-line text-sm">{{ $audit->scope }}</p> @endif
        </div>
        @can('update', $audit)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('audit.audits.edit', $audit) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::audits.ui.edit') }}</a>
                @if ($audit->status === \Modules\Audit\Domain\Enums\AuditStatus::Planned)
                    <button type="button" wire:click="changeStatus('in_progress')" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('audit::audits.ui.start') }}</button>
                @endif
                @if (in_array($audit->status, [\Modules\Audit\Domain\Enums\AuditStatus::Completed, \Modules\Audit\Domain\Enums\AuditStatus::Cancelled], true))
                    <button type="button" wire:click="changeStatus('in_progress')" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::audits.ui.reopen') }}</button>
                @endif
                @if (in_array($audit->status, [\Modules\Audit\Domain\Enums\AuditStatus::Planned, \Modules\Audit\Domain\Enums\AuditStatus::InProgress], true))
                    <button type="button" wire:click="changeStatus('cancelled')" wire:confirm="{{ __('audit::audits.ui.cancel_confirm') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-gray-300">{{ __('audit::audits.ui.cancel') }}</button>
                @endif
            </div>
        @endcan
    </header>

    <section aria-labelledby="conclusion-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="conclusion-heading" class="text-sm font-semibold">{{ __('audit::audits.fields.conclusion') }}</h2>
        @if ($audit->status === \Modules\Audit\Domain\Enums\AuditStatus::InProgress && auth()->user()?->can('update', $audit))
            <form wire:submit="changeStatus('completed')" class="space-y-2" novalidate>
                <label for="conclusion" class="sr-only">{{ __('audit::audits.fields.conclusion') }}</label>
                <textarea id="conclusion" wire:model="conclusion" rows="3" maxlength="10000" placeholder="{{ __('audit::audits.examples.conclusion') }}" class="block w-full"></textarea>
                @error('conclusion') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::audits.ui.complete') }}</button>
            </form>
        @else
            <p class="whitespace-pre-line text-sm">{{ $audit->conclusion ?? '—' }}</p>
            @error('conclusion') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
    </section>

    <section aria-labelledby="findings-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="findings-heading" class="text-sm font-semibold">{{ __('audit::audits.findings.title') }}</h2>
            @can('create', \Modules\Audit\Domain\Models\Finding::class)
                <a href="{{ route('audit.findings.create', ['audit' => $audit->id]) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::audits.findings.new') }}</a>
            @endcan
        </div>
        @include('audit::livewire.findings.table', ['findings' => $findings])
    </section>
</div>
