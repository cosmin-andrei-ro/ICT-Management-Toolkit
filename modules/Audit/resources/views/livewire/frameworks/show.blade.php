<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('audit.index') }}" class="text-sm text-gray-500 underline">{{ __('audit::compliance.ui.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $framework->name }} @if ($framework->version) <span class="text-base font-normal text-gray-500">{{ $framework->version }}</span> @endif</h1>
            @if ($framework->description) <p class="max-w-3xl text-sm text-gray-600">{{ $framework->description }}</p> @endif
            <p class="text-sm">
                {{ __('audit::compliance.ui.requirements', ['count' => $coverage['total']]) }}
                · {{ $coverage['implemented_percent'] !== null ? __('audit::compliance.ui.met', ['percent' => $coverage['implemented_percent']]) : __('audit::compliance.ui.not_measured') }}
                @if ($framework->has_soa) · {{ __('audit::compliance.ui.soa') }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
        @if ($framework->has_soa)
            <a href="{{ route('audit.frameworks.soa', $framework) }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::compliance.ui.soa') }}</a>
        @endif
        @can('update', $framework)
            <button type="button" wire:click="toggleArchived" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __($framework->is_active ? 'audit::compliance.ui.archive' : 'audit::compliance.ui.restore') }}</button>
        @endcan
        </div>
    </header>

    @if (! $framework->is_active)
        <p class="rounded-md bg-gray-100 p-3 text-sm text-gray-700" role="status">{{ __('audit::compliance.ui.archived') }}</p>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="search" class="block text-xs text-gray-600">{{ __('audit::compliance.ui.search') }}</label>
            <input id="search" type="search" wire:model.live.debounce.400ms="search" maxlength="200" class="mt-1 block w-56">
        </div>
        <div>
            <label for="state" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.state') }}</label>
            <select id="state" wire:model.live="state" class="mt-1 block">
                <option value="">{{ __('audit::compliance.ui.all') }}</option>
                @foreach ($states as $option) <option value="{{ $option->value }}" wire:key="state-{{ $option->value }}">{{ $option->label() }} ({{ $coverage['states'][$option->value] }})</option> @endforeach
            </select>
        </div>
    </div>

    @forelse ($groups as $group => $requirements)
        <section wire:key="group-{{ md5($group) }}" class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-200">
            @if ($group !== '') <h2 class="border-b border-gray-100 px-4 py-2 text-sm font-semibold">{{ $group }}</h2> @endif
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs text-gray-500">
                    <tr>
                        <th scope="col" class="w-24 px-4 py-2 font-medium">{{ __('audit::compliance.fields.reference') }}</th>
                        <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::compliance.fields.requirement') }}</th>
                        <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::compliance.fields.controls') }}</th>
                        <th scope="col" class="px-4 py-2 font-medium">{{ __('audit::compliance.fields.state') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($requirements as $requirement)
                        <tr wire:key="requirement-{{ $requirement->id }}" class="align-top">
                            <td class="px-4 py-2 font-mono text-xs">{{ $requirement->reference }}</td>
                            <td class="px-4 py-2">
                                <p>{{ $requirement->title }}</p>
                                @if ($requirement->description) <p class="mt-1 whitespace-pre-line text-xs text-gray-600">{{ $requirement->description }}</p> @endif
                                @if ($requirement->justification) <p class="mt-1 whitespace-pre-line text-xs text-gray-600"><span class="font-medium">{{ __('audit::compliance.fields.justification') }}:</span> {{ $requirement->justification }}</p> @endif

                                @if ($decidingId === $requirement->id)
                                    <form wire:submit="saveDecision" class="mt-3 space-y-3 rounded-md bg-gray-50 p-3" novalidate>
                                        <fieldset>
                                            <legend class="text-xs font-medium">{{ __('audit::compliance.fields.applicability') }}</legend>
                                            <div class="mt-1 flex flex-wrap gap-4 text-sm">
                                                <label class="flex items-center gap-1"><input type="radio" wire:model="applicability" value="yes"> {{ __('audit::compliance.ui.applicable_yes') }}</label>
                                                <label class="flex items-center gap-1"><input type="radio" wire:model="applicability" value="no"> {{ __('audit::compliance.ui.applicable_no') }}</label>
                                                <label class="flex items-center gap-1"><input type="radio" wire:model="applicability" value=""> {{ __('audit::compliance.ui.applicable_undecided') }}</label>
                                            </div>
                                        </fieldset>
                                        <div>
                                            <label for="justification" class="block text-xs font-medium">{{ __('audit::compliance.fields.justification') }}</label>
                                            <textarea id="justification" wire:model="justification" rows="2" maxlength="5000" class="mt-1 block w-full"></textarea>
                                            <p class="mt-1 text-xs text-gray-500">{{ __('audit::compliance.ui.justification_help') }}</p>
                                            @error('justification') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="requirementText" class="block text-xs font-medium">{{ __('audit::compliance.fields.requirement_text') }}</label>
                                            <textarea id="requirementText" wire:model="requirementText" rows="3" maxlength="10000" class="mt-1 block w-full"></textarea>
                                            <p class="mt-1 text-xs text-gray-500">{{ __('audit::compliance.ui.requirement_text_help') }}</p>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('audit::compliance.ui.save') }}</button>
                                            <button type="button" wire:click="cancel" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::compliance.ui.cancel') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @forelse ($requirement->controls as $control)
                                    <a href="{{ route('audit.controls.show', $control) }}" wire:key="requirement-{{ $requirement->id }}-control-{{ $control->id }}" class="block text-blue-700 underline">{{ $control->code() }} · {{ $control->name }}</a>
                                @empty
                                    <span class="text-gray-500">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-2">
                                <x-audit::state :state="$requirement->state()" />
                                @can('update', $framework)
                                    @if ($decidingId !== $requirement->id)
                                        <button type="button" wire:click="decide('{{ $requirement->id }}')" class="mt-1 block text-xs text-blue-700 underline">{{ __($requirement->is_applicable === null ? 'audit::compliance.ui.decide' : 'audit::compliance.ui.edit_decision') }}</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="text-sm text-gray-500">{{ __('audit::compliance.ui.no_requirements') }}</p>
    @endforelse

    @can('update', $framework)
        <form wire:submit="addRequirement" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200" novalidate>
            <h2 class="text-sm font-semibold">{{ __('audit::compliance.ui.add_requirement') }}</h2>
            <div class="grid gap-3 sm:grid-cols-4">
                <div>
                    <label for="newReference" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.reference') }}</label>
                    <input id="newReference" type="text" wire:model="newReference" maxlength="64" class="mt-1 block w-full">
                    @error('newReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="newTitle" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.requirement') }}</label>
                    <input id="newTitle" type="text" wire:model="newTitle" maxlength="255" class="mt-1 block w-full">
                    @error('newTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="newGroup" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.group') }}</label>
                    <input id="newGroup" type="text" wire:model="newGroup" maxlength="255" class="mt-1 block w-full">
                </div>
            </div>
            <div>
                <label for="newDescription" class="block text-xs text-gray-600">{{ __('audit::compliance.fields.requirement_text') }}</label>
                <textarea id="newDescription" wire:model="newDescription" rows="2" maxlength="10000" class="mt-1 block w-full"></textarea>
            </div>
            <button type="submit" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('audit::compliance.ui.add_requirement_button') }}</button>
        </form>
    @endcan

    <x-core::record-history :record="$framework" />
</div>
