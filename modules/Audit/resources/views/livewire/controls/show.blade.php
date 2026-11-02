<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('audit.controls.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::compliance.controls.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $control->code() }} · {{ $control->name }}</h1>
            <p class="text-sm text-gray-600">{{ __('audit::compliance.controls.fields.owner') }}: {{ $control->owner?->name ?? '—' }} · <x-audit::status :status="$control->status" /></p>
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
</div>
