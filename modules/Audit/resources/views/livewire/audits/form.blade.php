<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $auditId ? route('audit.audits.show', $auditId) : route('audit.audits.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::audits.ui.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($auditId ? 'audit::audits.ui.edit' : 'audit::audits.ui.new') }}</h1>
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="title" class="block text-sm font-medium">{{ __('audit::audits.fields.title') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="title" type="text" wire:model="title" maxlength="255" placeholder="{{ __('audit::audits.examples.title') }}" class="mt-1 block w-full">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="kind" class="block text-sm font-medium">{{ __('audit::audits.fields.kind') }}</label>
                <select id="kind" wire:model.live="kind" class="mt-1 block w-full">
                    @foreach ($kinds as $option) <option value="{{ $option->value }}" wire:key="kind-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="leadAuditorId" class="block text-sm font-medium">{{ __('audit::audits.fields.lead_auditor') }}</label>
                <select id="leadAuditorId" wire:model="leadAuditorId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="lead-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                </select>
                @error('leadAuditorId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        @if ($kind === 'external')
            <div>
                <label for="auditBody" class="block text-sm font-medium">{{ __('audit::audits.fields.audit_body') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="auditBody" type="text" wire:model="auditBody" maxlength="255" placeholder="{{ __('audit::audits.examples.audit_body') }}" class="mt-1 block w-full">
                @error('auditBody') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="startsOn" class="block text-sm font-medium">{{ __('audit::audits.fields.starts_on') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <input id="startsOn" type="date" wire:model="startsOn" class="mt-1 block w-full">
                @error('startsOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="endsOn" class="block text-sm font-medium">{{ __('audit::audits.fields.ends_on') }}</label>
                <input id="endsOn" type="date" wire:model="endsOn" class="mt-1 block w-full">
                @error('endsOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="scope" class="block text-sm font-medium">{{ __('audit::audits.fields.scope') }}</label>
            <textarea id="scope" wire:model="scope" rows="3" maxlength="10000" placeholder="{{ __('audit::audits.examples.scope') }}" class="mt-1 block w-full"></textarea>
        </div>
        <fieldset>
            <legend class="text-sm font-medium">{{ __('audit::audits.fields.frameworks') }}</legend>
            <div class="mt-1 flex flex-wrap gap-4 text-sm">
                @forelse ($frameworks as $framework)
                    <label wire:key="framework-{{ $framework->id }}" class="flex items-center gap-1"><input type="checkbox" wire:model="frameworkIds" value="{{ $framework->id }}"> {{ $framework->name }}</label>
                @empty
                    <span class="text-gray-500">{{ __('audit::compliance.ui.none') }}</span>
                @endforelse
            </div>
        </fieldset>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::audits.ui.save') }}</button>
    </form>
</div>
