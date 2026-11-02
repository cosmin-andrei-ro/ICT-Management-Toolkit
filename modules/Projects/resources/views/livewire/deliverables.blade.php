<div class="grid gap-6 lg:grid-cols-3">
    <section aria-labelledby="deliverables-heading" @class(['space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200', 'lg:col-span-2' => $isApplication, 'lg:col-span-3' => ! $isApplication])>
        <h2 id="deliverables-heading" class="text-sm font-semibold">{{ __('projects::deliverables.heading') }}</h2>
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($deliverables as $deliverable)
                @php($record = $deliverable->linked_type ? ($resolved[$deliverable->linked_type.':'.$deliverable->linked_id] ?? null) : null)
                <li wire:key="deliverable-{{ $deliverable->id }}" class="space-y-1 py-2">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <span class="font-medium">{{ $deliverable->title }}</span>
                            <span class="text-xs text-gray-500">· {{ $deliverable->phase->key->label() }} · {{ $deliverable->required ? __('projects::deliverables.required') : __('projects::deliverables.optional') }}</span>
                            <span class="block text-xs">
                                @if ($deliverable->isDelivered())
                                    <span class="font-medium text-green-800">✓ {{ __('projects::deliverables.delivered_on', ['date' => $deliverable->delivered_at?->toDateString(), 'name' => $deliverable->deliverer?->name ?? '—']) }}</span>
                                @else
                                    <span class="text-gray-600">{{ __('projects::deliverables.not_delivered') }}</span>
                                @endif
                            </span>
                            @if ($deliverable->linked_type)
                                @php($kind = $kinds[$deliverable->linked_type] ?? $deliverable->linked_type)
                                @php($shown = $record?->title ?? $deliverable->linked_title ?? '')
                                <span class="block text-xs">@unless (str_starts_with($shown, $kind)){{ $kind }}: @endunless
                                    @if ($record) <a href="{{ $record->url }}" class="text-blue-700 underline">{{ $record->title }}</a> @else {{ $deliverable->linked_title }} @endif
                                </span>
                            @endif
                        </div>
                        @if ($canManage)
                            <div class="flex flex-wrap gap-3 text-xs">
                                @if ($deliverable->isDelivered())
                                    <button type="button" wire:click="undeliver('{{ $deliverable->id }}')" class="font-medium text-blue-700 underline">{{ __('projects::deliverables.undeliver') }}<span class="sr-only">: {{ $deliverable->title }}</span></button>
                                @else
                                    <button type="button" wire:click="deliver('{{ $deliverable->id }}')" class="font-medium text-blue-700 underline">{{ __('projects::deliverables.deliver') }}<span class="sr-only">: {{ $deliverable->title }}</span></button>
                                @endif
                                @if ($deliverable->linked_type)
                                    <button type="button" wire:click="unlink('{{ $deliverable->id }}')" class="font-medium text-blue-700 underline">{{ __('projects::deliverables.unlink') }}<span class="sr-only">: {{ $deliverable->title }}</span></button>
                                @else
                                    <button type="button" wire:click="startLinking('{{ $deliverable->id }}')" class="font-medium text-blue-700 underline">{{ __('projects::deliverables.link') }}<span class="sr-only">: {{ $deliverable->title }}</span></button>
                                @endif
                                <button type="button" wire:click="delete('{{ $deliverable->id }}')" wire:confirm="{{ __('projects::deliverables.delete_confirm') }}" class="font-medium text-red-700 underline">{{ __('projects::projects.delete') }}<span class="sr-only">: {{ $deliverable->title }}</span></button>
                            </div>
                        @endif
                    </div>
                    @if ($linkingId === $deliverable->id)
                        <div class="space-y-2 rounded-md bg-gray-50 p-2 text-xs">
                            <div class="flex flex-wrap gap-2">
                                <label for="linkType" class="sr-only">{{ __('projects::deliverables.kind') }}</label>
                                <select id="linkType" wire:model.live="linkType" class="rounded-md border-gray-300 text-xs">
                                    <option value="">{{ __('projects::deliverables.choose_kind') }}</option>
                                    @foreach ($kinds as $type => $label)
                                        <option wire:key="kind-{{ $type }}" value="{{ $type }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label for="linkTerm" class="sr-only">{{ __('projects::deliverables.search') }}</label>
                                <input id="linkTerm" type="search" wire:model.live.debounce.300ms="linkTerm" placeholder="{{ __('projects::deliverables.search') }}" class="rounded-md border-gray-300 text-xs">
                                <button type="button" wire:click="cancelLinking" class="rounded px-2 ring-1 ring-gray-300">{{ __('projects::projects.cancel') }}</button>
                            </div>
                            @error('linkTerm') <p class="text-red-600">{{ $message }}</p> @enderror
                            <ul class="divide-y divide-gray-100 rounded bg-white ring-1 ring-gray-200">
                                @foreach ($candidates as $candidate)
                                    <li wire:key="candidate-{{ $candidate->id }}"><button type="button" wire:click="link('{{ $candidate->id }}')" class="w-full px-2 py-1 text-left hover:bg-gray-50">{{ $candidate->title }} @if ($candidate->status) <span class="text-gray-500">· {{ $candidate->status }}</span> @endif</button></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </li>
            @empty
                <li class="py-2 text-gray-600">{{ __('projects::deliverables.none') }}</li>
            @endforelse
        </ul>

        @if ($canManage)
            <form wire:submit="add" class="flex flex-wrap items-end gap-3 text-sm" novalidate>
                <div class="grow">
                    <label for="deliverableTitle" class="block font-medium">{{ __('projects::deliverables.title') }}</label>
                    <input id="deliverableTitle" type="text" maxlength="200" wire:model="deliverableTitle" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="deliverablePhaseId" class="block font-medium">{{ __('projects::projects.fields.phase') }}</label>
                    <select id="deliverablePhaseId" wire:model="deliverablePhaseId" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($project->phases as $phase)
                            <option wire:key="deliverable-phase-{{ $phase->id }}" value="{{ $phase->id }}">{{ $phase->key->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 pb-2"><input type="checkbox" wire:model="deliverableRequired" class="rounded border-gray-300"> {{ __('projects::deliverables.required') }}</label>
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('projects::deliverables.add') }}</button>
            </form>
            @foreach (['deliverableTitle', 'deliverablePhaseId'] as $field) @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror @endforeach
        @endif

        @if ($blockers !== [])
            <div class="rounded-md bg-amber-50 p-3 text-xs text-amber-900 ring-1 ring-amber-200">
                <p class="font-medium">{{ __('projects::deliverables.blockers') }}</p>
                <ul class="list-disc pl-4">
                    @foreach ($blockers as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @if ($isApplication)
        <section aria-labelledby="checklist-heading" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <h2 id="checklist-heading" class="text-sm font-semibold">{{ __('projects::deliverables.checklist_heading') }}</h2>
            @if ($application)
                <p class="text-sm"><a href="{{ $application->url }}" class="font-medium text-blue-700 underline">{{ $application->code }} · {{ $application->name }}</a></p>
            @endif
            @if ($canManage && $project->isOpen())
                <div class="text-sm">
                    <label for="applicationTerm" class="block text-xs font-medium">{{ $application ? __('projects::deliverables.change_application') : __('projects::deliverables.choose_application') }}</label>
                    <input id="applicationTerm" type="search" wire:model.live.debounce.300ms="applicationTerm" placeholder="{{ __('projects::deliverables.application_search') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @if ($applicationCandidates !== [])
                        <ul class="mt-1 divide-y divide-gray-100 rounded ring-1 ring-gray-200">
                            @foreach ($applicationCandidates as $candidate)
                                <li wire:key="app-{{ $candidate->id }}"><button type="button" wire:click="chooseApplication('{{ $candidate->id }}')" class="w-full px-2 py-1 text-left hover:bg-gray-50">{{ $candidate->code }} · {{ $candidate->name }}</button></li>
                            @endforeach
                        </ul>
                    @endif
                    @error('applicationId') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            <ul class="space-y-1 text-sm">
                @foreach ($checklist as $check => $done)
                    <li wire:key="check-{{ $check }}" class="flex items-start gap-2">
                        <span @class(['mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold', 'bg-green-600 text-white' => $done, 'bg-gray-200 text-gray-600' => ! $done]) aria-hidden="true">{{ $done ? '✓' : '·' }}</span>
                        <span>{{ __('projects::deliverables.items.'.$check) }} <span class="sr-only">— {{ $done ? __('projects::deliverables.done') : __('projects::deliverables.to_do') }}</span></span>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-gray-500">{{ __('projects::deliverables.checklist_help') }}</p>
        </section>
    @endif
</div>
