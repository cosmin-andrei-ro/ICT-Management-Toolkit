<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-1">
        <a href="{{ $findingId ? route('audit.findings.show', $findingId) : ($audit ? route('audit.audits.show', $audit) : route('audit.findings.index')) }}" class="text-sm text-gray-500 underline">{{ __('audit::audits.findings.back') }}</a>
        <h1 class="text-xl font-semibold">{{ __($findingId ? 'audit::audits.findings.edit' : 'audit::audits.findings.new') }}</h1>
        @if ($audit) <p class="text-sm text-gray-600">{{ $audit->code() }} · {{ $audit->title }}</p> @endif
        @if ($testId) <p class="text-sm text-gray-600">{{ __('audit::audits.findings.from_test') }}</p> @endif
    </header>

    <form wire:submit="save" class="space-y-5 rounded-lg bg-white p-6 ring-1 ring-gray-200" novalidate>
        <div>
            <label for="title" class="block text-sm font-medium">{{ __('audit::audits.findings.fields.title') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <input id="title" type="text" wire:model="title" maxlength="255" placeholder="{{ __('audit::audits.examples.finding_title') }}" class="mt-1 block w-full">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">{{ __('audit::audits.findings.fields.description') }} <span class="text-red-600" aria-hidden="true">*</span></label>
            <textarea id="description" wire:model="description" rows="4" maxlength="10000" placeholder="{{ __('audit::audits.examples.finding_description') }}" class="mt-1 block w-full"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="severity" class="block text-sm font-medium">{{ __('audit::audits.findings.fields.severity') }}</label>
                <select id="severity" wire:model="severity" class="mt-1 block w-full">
                    @foreach ($severities as $option) <option value="{{ $option->value }}" wire:key="severity-{{ $option->value }}">{{ $option->label() }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="ownerId" class="block text-sm font-medium">{{ __('audit::audits.findings.fields.owner') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                <select id="ownerId" wire:model="ownerId" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($people as $person) <option value="{{ $person->id }}" wire:key="owner-{{ $person->id }}">{{ $person->name }}</option> @endforeach
                </select>
                @error('ownerId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="dueOn" class="block text-sm font-medium">{{ __('audit::audits.findings.fields.due_on') }}</label>
                <input id="dueOn" type="date" wire:model="dueOn" class="mt-1 block w-full">
                @error('dueOn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <p class="text-xs text-gray-500">{{ __('audit::audits.findings.severity_help') }}</p>
        <div>
            <label for="controlId" class="block text-sm font-medium">{{ __('audit::audits.findings.fields.control') }}</label>
            <select id="controlId" wire:model="controlId" class="mt-1 block w-full">
                <option value="">—</option>
                @foreach ($controls as $control) <option value="{{ $control->id }}" wire:key="control-{{ $control->id }}">{{ $control->code() }} · {{ $control->name }}</option> @endforeach
            </select>
            @error('controlId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="space-y-2">
            <span class="block text-sm font-medium">{{ __('audit::audits.findings.fields.requirement') }}</span>
            @if ($requirement)
                <p class="text-sm">{{ $requirement->framework->name }} <span class="font-mono text-xs">{{ $requirement->reference }}</span> {{ $requirement->title }}
                    <button type="button" wire:click="clearRequirement" class="ml-2 text-xs text-red-700 underline">{{ __('audit::compliance.controls.unmap') }}</button></p>
            @else
                <label for="requirementSearch" class="sr-only">{{ __('audit::compliance.ui.search') }}</label>
                <input id="requirementSearch" type="search" wire:model.live.debounce.400ms="requirementSearch" maxlength="200" placeholder="{{ __('audit::audits.findings.requirement_search') }}" class="block w-full">
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($candidates as $candidate)
                        <li wire:key="candidate-{{ $candidate->id }}" class="flex flex-wrap items-center justify-between gap-2 py-1">
                            <span>{{ $candidate->framework->name }} <span class="font-mono text-xs">{{ $candidate->reference }}</span> {{ $candidate->title }}</span>
                            <button type="button" wire:click="chooseRequirement('{{ $candidate->id }}')" class="rounded-md bg-white px-2 py-1 text-xs font-semibold ring-1 ring-gray-300">{{ __('audit::audits.findings.choose') }}</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('audit::audits.findings.save') }}</button>
    </form>
</div>
