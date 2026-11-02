<div class="space-y-6">
    <div>
        <a href="{{ route('audit.audits.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::audits.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __('audit::audits.findings.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('audit::audits.findings.intro') }}</p>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="block text-xs text-gray-600">{{ __('audit::audits.findings.fields.status') }}</label>
            <select id="status" wire:model.live="status" class="mt-1 block">
                <option value="open">{{ __('audit::audits.findings.not_closed') }}</option>
                <option value="closed">{{ __('audit::audits.finding_statuses.closed') }}</option>
                <option value="">{{ __('audit::compliance.ui.all') }}</option>
            </select>
        </div>
        <div>
            <label for="severity" class="block text-xs text-gray-600">{{ __('audit::audits.findings.fields.severity') }}</label>
            <select id="severity" wire:model.live="severity" class="mt-1 block">
                <option value="">{{ __('audit::compliance.ui.all') }}</option>
                @foreach ($severities as $option) <option value="{{ $option->value }}" wire:key="severity-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="overdue"> {{ __('audit::audits.findings.overdue') }}</label>
    </div>

    <div class="rounded-lg bg-white px-2 ring-1 ring-gray-200">
        @include('audit::livewire.findings.table', ['findings' => $findings])
    </div>
    {{ $findings->links() }}
</div>
