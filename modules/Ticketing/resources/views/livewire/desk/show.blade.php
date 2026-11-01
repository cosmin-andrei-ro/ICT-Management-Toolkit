<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <header class="space-y-2">
            <a href="{{ route('ticketing.desk.index') }}" class="text-sm text-gray-500 underline">{{ __('ticketing::ui.desk.back') }}</a>
            <h1 class="text-xl font-semibold"><span class="font-mono text-gray-500">{{ $ticket->number }}</span> {{ $ticket->title }}</h1>
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <x-ticketing::badges :ticket="$ticket" />
                <span>{{ $ticket->type->label() }} · {{ $ticket->channel->label() }}</span>
                <span>· {{ __('ticketing::ui.requested_by', ['name' => $ticket->requester->name]) }}</span>
            </div>
        </header>

        @error('action') <p class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p> @enderror

        <section class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
            <p class="whitespace-pre-line text-sm text-gray-800">{{ $ticket->description }}</p>
        </section>

        <x-ticketing::service-answers :ticket="$ticket" />

        @if ($ticket->type->value === 'problem')
            <section class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200" aria-labelledby="analysis-heading">
                <div class="flex items-center justify-between">
                    <h2 id="analysis-heading" class="text-sm font-semibold">{{ __('ticketing::problems.ui.analysis') }}</h2>
                    @if ($ticket->problemDetails?->isKnownError())
                        <span class="rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800">{{ __('ticketing::problems.ui.known_error') }}</span>
                    @endif
                </div>
                @can('manageProblem', $ticket)
                    <form wire:submit="saveAnalysis" class="space-y-3">
                        <label class="block text-sm">
                            <span class="font-medium">{{ __('ticketing::problems.ui.root_cause') }}</span>
                            <textarea wire:model="rootCause" rows="3" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium">{{ __('ticketing::problems.ui.workaround') }}</span>
                            <textarea wire:model="workaround" rows="3" class="mt-1 block w-full rounded-md border-gray-300"></textarea>
                        </label>
                        @error('workaround') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <div class="flex items-center gap-3">
                            <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::ui.save') }}</button>
                            @unless ($ticket->problemDetails?->isKnownError())
                                <button type="button" wire:click="markKnownError" class="rounded-md bg-white px-3 py-1.5 text-sm ring-1 ring-gray-300">{{ __('ticketing::problems.ui.mark_known_error') }}</button>
                            @endunless
                        </div>
                    </form>
                @else
                    <dl class="space-y-2 text-sm">
                        <dt class="font-medium">{{ __('ticketing::problems.ui.root_cause') }}</dt>
                        <dd class="whitespace-pre-line text-gray-700">{{ $ticket->problemDetails?->root_cause ?? '—' }}</dd>
                        <dt class="font-medium">{{ __('ticketing::problems.ui.workaround') }}</dt>
                        <dd class="whitespace-pre-line text-gray-700">{{ $ticket->problemDetails?->workaround ?? '—' }}</dd>
                    </dl>
                @endcan
            </section>

            <section class="rounded-lg bg-white p-4 ring-1 ring-gray-200" aria-labelledby="incidents-heading">
                <h2 id="incidents-heading" class="mb-2 text-sm font-semibold">{{ __('ticketing::problems.ui.linked_incidents', ['count' => $linkedIncidents->count()]) }}</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($linkedIncidents as $incident)
                        <li wire:key="incident-{{ $incident->id }}" class="flex items-center justify-between py-1.5">
                            <a href="{{ route('ticketing.desk.show', $incident) }}" class="text-blue-700 underline"><span class="font-mono">{{ $incident->number }}</span> {{ $incident->title }}</a>
                            <span class="text-xs text-gray-500">{{ $incident->status->label() }}</span>
                        </li>
                    @empty
                        <li class="py-1.5 text-gray-500">{{ __('ticketing::problems.ui.no_incidents') }}</li>
                    @endforelse
                </ul>
            </section>
        @endif

        <x-ticketing::conversation :comments="$comments" :attachments="$attachments" :show-internal="true" />

        @can('comment', $ticket)
            <form wire:submit="addComment" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <label for="comment" class="block text-sm font-medium">{{ __('ticketing::ui.reply') }}</label>
                <textarea id="comment" wire:model="comment" rows="4" class="block w-full rounded-md border-gray-300"></textarea>
                @error('comment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <input type="file" wire:model="files" multiple class="block w-full text-sm" aria-label="{{ __('ticketing::ui.fields.attachments') }}">
                @error('files.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="internal"> {{ __('ticketing::ui.desk.internal_toggle') }}
                    </label>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('ticketing::ui.send') }}</button>
                </div>
            </form>
        @endcan

        <details class="rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200">
            <summary class="cursor-pointer font-medium">{{ __('ticketing::ui.desk.history') }}</summary>
            <ol class="mt-3 space-y-1 text-gray-600">
                @foreach ($transitions as $transition)
                    <li wire:key="transition-{{ $transition->id }}">
                        {{ $transition->created_at->format('Y-m-d H:i') }} —
                        {{ $transition->from_status?->label() ?? '∅' }} → {{ $transition->to_status->label() }}
                        ({{ $transition->user?->name ?? __('ticketing::ui.system') }})
                    </li>
                @endforeach
            </ol>
        </details>
    </div>

    <aside class="space-y-6">
        @if ($ticket->type->value === 'incident')
            <section class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200" aria-labelledby="problem-heading">
                <h2 id="problem-heading" class="text-sm font-semibold">{{ __('ticketing::problems.ui.problem') }}</h2>
                @if ($ticket->problem)
                    <p class="text-sm">
                        <a href="{{ route('ticketing.desk.show', $ticket->problem) }}" class="text-blue-700 underline"><span class="font-mono">{{ $ticket->problem->number }}</span> {{ $ticket->problem->title }}</a>
                        @if ($ticket->problem->problemDetails?->isKnownError())
                            <span class="ml-1 rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800">{{ __('ticketing::problems.ui.known_error') }}</span>
                        @endif
                    </p>
                @endif
                @can('linkProblem', $ticket)
                    <form wire:submit="linkProblem" class="flex gap-2">
                        <select wire:model="problemId" class="block w-full rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::problems.ui.problem') }}">
                            <option value="">—</option>
                            @foreach ($openProblems as $option)
                                <option value="{{ $option->id }}">{{ $option->number }} — {{ \Illuminate\Support\Str::limit($option->title, 50) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::problems.ui.link') }}</button>
                    </form>
                    @error('problemId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex gap-3 text-sm">
                        @if ($ticket->problem_id)
                            <button type="button" wire:click="unlinkProblem" class="underline">{{ __('ticketing::problems.ui.unlink') }}</button>
                        @endif
                        @can('createProblem', \Modules\Ticketing\Domain\Models\Ticket::class)
                            <button type="button" wire:click="createProblemFromIncident" class="underline">{{ __('ticketing::problems.ui.create_from_incident') }}</button>
                        @endcan
                    </div>
                @endcan
            </section>
        @endif

        @if ($slaTimers->isNotEmpty())
            <section class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200" aria-labelledby="sla-heading">
                <h2 id="sla-heading" class="text-sm font-semibold">{{ __('ticketing::sla.title') }}</h2>
                <dl class="space-y-2 text-sm">
                    @foreach ($slaTimers as $timer)
                        <div wire:key="sla-{{ $timer->id }}" class="flex items-center justify-between gap-2">
                            <dt>
                                {{ $timer->metric->label() }}
                                <span class="block text-xs text-gray-500">
                                    {{ __('ticketing::sla.target') }}: {{ $timer->target_minutes >= 60 ? __('ticketing::sla.hours', ['count' => round($timer->target_minutes / 60, 1)]) : __('ticketing::sla.minutes', ['count' => $timer->target_minutes]) }}
                                </span>
                            </dt>
                            <dd><x-ticketing::sla-badge :timer="$timer" /></dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($ticket->status->isOpen())
            <section class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 class="text-sm font-semibold">{{ __('ticketing::ui.desk.status') }}</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($workingTargets as $target)
                        <button type="button" wire:key="status-{{ $target->value }}" wire:click="changeStatus('{{ $target->value }}')" class="rounded-md bg-white px-3 py-1.5 text-sm ring-1 ring-gray-300 hover:bg-gray-50">{{ $target->label() }}</button>
                    @endforeach
                </div>
            </section>

            @can('assign', $ticket)
                <form wire:submit="assign" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                    <h2 class="text-sm font-semibold">{{ __('ticketing::ui.desk.assignment') }}</h2>
                    <label class="block text-sm">
                        <span class="block font-medium">{{ __('ticketing::ui.fields.queue') }}</span>
                        <select wire:model.live="queueId" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">—</option>
                            @foreach ($queues as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="block font-medium">{{ __('ticketing::ui.fields.assignee') }}</span>
                        <select wire:model="assigneeId" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">{{ __('ticketing::ui.filters.unassigned') }}</option>
                            @foreach ($assignees as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('assign') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex items-center justify-between">
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::ui.save') }}</button>
                        <button type="button" wire:click="assignToMe" class="text-sm underline">{{ __('ticketing::ui.desk.assign_to_me') }}</button>
                    </div>
                </form>
            @endcan

            <form wire:submit="resolve" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 class="text-sm font-semibold">{{ __('ticketing::ui.desk.resolve') }}</h2>
                <select wire:model="resolutionCode" class="block w-full rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::ui.fields.resolution_code') }}">
                    @foreach ($resolutionCodes as $code)
                        <option value="{{ $code->value }}">{{ $code->label() }}</option>
                    @endforeach
                </select>
                <textarea wire:model="resolutionNote" rows="3" class="block w-full rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::ui.fields.resolution_note') }}" placeholder="{{ __('ticketing::ui.desk.resolution_placeholder') }}"></textarea>
                @error('resolutionNote') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($ticket->type->value === 'problem' && $linkedIncidents->isNotEmpty())
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="resolveIncidents"> {{ __('ticketing::problems.ui.resolve_incidents', ['count' => $linkedIncidents->whereIn('status', \Modules\Ticketing\Domain\Enums\TicketStatus::open())->count()]) }}
                    </label>
                @endif
                <button type="submit" class="rounded-md bg-green-700 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::ui.desk.resolve') }}</button>
            </form>

            <form wire:submit="updateDetails" class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 class="text-sm font-semibold">{{ __('ticketing::ui.desk.triage') }}</h2>
                <input type="text" wire:model="title" class="block w-full rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::ui.fields.title') }}">
                @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <textarea wire:model="description" rows="3" class="block w-full rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::ui.fields.description') }}"></textarea>
                <select wire:model="categoryId" class="block w-full rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::ui.fields.category') }}">
                    <option value="">—</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->fullName() }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <select wire:model="impact" class="rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::ui.fields.impact') }}">
                        @foreach ($impacts as $option)
                            <option value="{{ $option->value }}">{{ __('ticketing::ui.fields.impact') }}: {{ ucfirst($option->value) }}</option>
                        @endforeach
                    </select>
                    <select wire:model="urgency" class="rounded-md border-gray-300 text-sm" aria-label="{{ __('ticketing::ui.fields.urgency') }}">
                        @foreach ($urgencies as $option)
                            <option value="{{ $option->value }}">{{ __('ticketing::ui.fields.urgency') }}: {{ ucfirst($option->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::ui.save') }}</button>
            </form>
        @endif

        @if ($ticket->status->value === 'resolved')
            <section class="space-y-3 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <h2 class="text-sm font-semibold">{{ __('ticketing::ui.desk.resolved') }}</h2>
                <p class="text-sm text-gray-600">{{ $ticket->resolution_code?->label() }}</p>
                <div class="flex gap-2">
                    <button type="button" wire:click="reopen" class="rounded-md bg-white px-3 py-1.5 text-sm ring-1 ring-gray-300">{{ __('ticketing::ui.reopen') }}</button>
                    <button type="button" wire:click="close" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white">{{ __('ticketing::ui.desk.close') }}</button>
                </div>
            </section>
        @endif

        @can('cancel', $ticket)
            <form wire:submit="cancel" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <label for="cancel-reason" class="block text-sm font-semibold">{{ __('ticketing::ui.desk.cancel') }}</label>
                <input id="cancel-reason" type="text" wire:model="reason" class="block w-full rounded-md border-gray-300 text-sm" placeholder="{{ __('ticketing::ui.desk.cancel_reason') }}">
                @error('reason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="text-sm text-red-700 underline">{{ __('ticketing::ui.desk.cancel') }}</button>
            </form>
        @endcan
    </aside>
</div>
