<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 underline">{{ __('projects::projects.back') }}</a>
            <h1 class="text-xl font-semibold">{{ $project->code }} · {{ $project->name }}</h1>
            <p class="text-sm text-gray-600">
                {{ $project->kind->label() }} · {{ $project->status->label() }}
                · {{ __('projects::projects.fields.manager') }}: {{ $project->manager?->name ?? '—' }}
                · {{ __('projects::projects.fields.sponsor') }}: {{ $project->sponsor?->name ?? '—' }}
                @if ($project->planned_start || $project->planned_end) · {{ $project->planned_start?->toDateString() ?? '…' }} → {{ $project->planned_end?->toDateString() ?? '…' }} @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('projects.report', $project) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('projects::reports.print') }}</a>
        @if ($canManage)
            <a href="{{ route('projects.edit', $project) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold ring-1 ring-gray-300">{{ __('projects::projects.edit') }}</a>
        @endif
        </div>
    </header>
    @if ($project->description) <p class="whitespace-pre-line text-sm text-gray-700">{{ $project->description }}</p> @endif

    <section aria-labelledby="phases-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="phases-heading" class="text-sm font-semibold">{{ __('projects::projects.phases_heading') }}</h2>
        <ol class="grid gap-3 sm:grid-cols-4">
            @foreach ($project->phases as $phase)
                <li wire:key="phase-{{ $phase->id }}" @class(['rounded-md p-3 text-sm ring-1', 'bg-blue-50 ring-blue-300' => $phase->status === \Modules\Projects\Domain\Enums\PhaseStatus::Active, 'bg-green-50 ring-green-200' => $phase->status === \Modules\Projects\Domain\Enums\PhaseStatus::Done, 'bg-white ring-gray-200' => $phase->status === \Modules\Projects\Domain\Enums\PhaseStatus::Pending])>
                    <p class="font-medium">{{ $phase->position }}. {{ $phase->key->label() }}</p>
                    <p class="text-xs text-gray-600">{{ $phase->status->label() }}</p>
                    @if ($editingPhaseId === $phase->id)
                        <form wire:submit="savePhase" class="mt-2 space-y-1 text-xs" novalidate>
                            <label class="block">{{ __('projects::projects.fields.planned_start') }} <input type="date" wire:model="phaseStart" class="mt-0.5 block w-full rounded border-gray-300 text-xs"></label>
                            <label class="block">{{ __('projects::projects.fields.planned_end') }} <input type="date" wire:model="phaseEnd" class="mt-0.5 block w-full rounded border-gray-300 text-xs"></label>
                            @error('phaseDates') <p class="text-red-600">{{ $message }}</p> @enderror
                            <button type="submit" class="rounded bg-gray-900 px-2 py-1 font-semibold text-white">{{ __('projects::projects.save') }}</button>
                        </form>
                    @else
                        <p class="text-xs text-gray-600">{{ $phase->planned_start?->toDateString() ?? '…' }} → {{ $phase->planned_end?->toDateString() ?? '…' }}</p>
                        @if ($canManage)
                            <button type="button" wire:click="editPhase('{{ $phase->id }}')" class="mt-1 text-xs text-blue-700 underline">{{ __('projects::projects.plan_dates') }}<span class="sr-only">: {{ $phase->key->label() }}</span></button>
                        @endif
                    @endif
                </li>
            @endforeach
        </ol>
    </section>

    @livewire(\Modules\Projects\Http\Livewire\ProjectGovernance::class, ['project' => $project], key('governance-'.$project->id))

    @livewire(\Modules\Projects\Http\Livewire\ProjectDeliverables::class, ['project' => $project], key('deliverables-'.$project->id))

    @livewire(\Modules\Projects\Http\Livewire\ProjectBudget::class, ['project' => $project], key('budget-'.$project->id))

    <section aria-labelledby="tasks-heading" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="tasks-heading" class="text-sm font-semibold">{{ __('projects::projects.tasks') }}</h2>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <label for="phaseFilter" class="sr-only">{{ __('projects::projects.fields.phase') }}</label>
                <select id="phaseFilter" wire:model.live="phase" class="rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('projects::projects.all_phases') }}</option>
                    @foreach ($project->phases as $option)
                        <option wire:key="filter-{{ $option->id }}" value="{{ $option->id }}">{{ $option->key->label() }}</option>
                    @endforeach
                </select>
                <div class="flex" role="group" aria-label="{{ __('projects::projects.display') }}">
                    @foreach (['board', 'list'] as $mode)
                        <button type="button" wire:key="mode-{{ $mode }}" wire:click="showAs('{{ $mode }}')" aria-pressed="{{ $display === $mode ? 'true' : 'false' }}" @class(['px-3 py-1.5 ring-1', 'bg-gray-900 text-white ring-gray-900' => $display === $mode, 'bg-white ring-gray-300' => $display !== $mode, 'rounded-l-md' => $loop->first, 'rounded-r-md' => $loop->last])>{{ __('projects::projects.displays.'.$mode) }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($display === 'board')
            <div class="grid gap-3 md:grid-cols-4">
                @foreach ($columns as $column)
                    <section wire:key="column-{{ $column->value }}" aria-labelledby="column-{{ $column->value }}" class="space-y-2 rounded-lg bg-gray-100 p-2">
                        <h3 id="column-{{ $column->value }}" class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-600">{{ $column->label() }} ({{ $tasks->where('status', $column)->count() }})</h3>
                        @foreach ($tasks->where('status', $column) as $task)
                            <div wire:key="card-{{ $task->id }}"><x-projects::task-card :task="$task" :can-manage="$canManage" :user="$user" /></div>
                        @endforeach
                    </section>
                @endforeach
            </div>
        @else
            <div class="overflow-x-auto rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.task') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.phase') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.assignee') }}</th>
                            <th scope="col" class="py-2 pr-4 font-medium">{{ __('projects::projects.fields.due_on') }}</th>
                            <th scope="col" class="py-2 font-medium">{{ __('projects::projects.fields.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tasks->sortBy(fn ($task) => [$task->phase->position, $task->due_on?->timestamp ?? PHP_INT_MAX]) as $task)
                            <tr wire:key="row-{{ $task->id }}">
                                <td class="py-2 pr-4">{{ $task->title }}</td>
                                <td class="py-2 pr-4">{{ $task->phase->key->label() }}</td>
                                <td class="py-2 pr-4">{{ $task->assignee?->name ?? '—' }}</td>
                                <td @class(['py-2 pr-4', 'font-medium text-red-700' => $task->isOverdue()])>{{ $task->due_on?->toDateString() ?? '—' }}</td>
                                <td class="py-2">{{ $task->status->label() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-gray-600">{{ __('projects::projects.no_tasks') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canManage)
            <form wire:submit="saveTask" class="grid gap-3 rounded-lg bg-white p-4 text-sm ring-1 ring-gray-200 sm:grid-cols-4" novalidate>
                <h3 class="font-semibold sm:col-span-4">{{ $editingTaskId ? __('projects::projects.edit_task') : __('projects::projects.add_task') }}</h3>
                <div class="sm:col-span-2">
                    <label for="taskTitle" class="block font-medium">{{ __('projects::projects.fields.task') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <input id="taskTitle" type="text" maxlength="200" wire:model="taskTitle" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    @error('taskTitle') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="taskPhaseId" class="block font-medium">{{ __('projects::projects.fields.phase') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                    <select id="taskPhaseId" wire:model="taskPhaseId" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($project->phases as $option)
                            <option wire:key="task-phase-{{ $option->id }}" value="{{ $option->id }}">{{ $option->key->label() }}</option>
                        @endforeach
                    </select>
                    @error('taskPhaseId') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="taskDueOn" class="block font-medium">{{ __('projects::projects.fields.due_on') }}</label>
                    <input id="taskDueOn" type="date" wire:model="taskDueOn" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="taskAssigneeId" class="block font-medium">{{ __('projects::projects.fields.assignee') }}</label>
                    <select id="taskAssigneeId" wire:model="taskAssigneeId" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('projects::projects.unassigned') }}</option>
                        @foreach ($team as $person)
                            <option wire:key="assignee-{{ $person->id }}" value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                    @error('taskAssigneeId') <p class="mt-1 text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-3">
                    <label for="taskDescription" class="block font-medium">{{ __('projects::projects.fields.description') }}</label>
                    <input id="taskDescription" type="text" maxlength="5000" wire:model="taskDescription" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="flex gap-2 sm:col-span-4">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('projects::projects.save') }}</button>
                    @if ($editingTaskId) <button type="button" wire:click="cancelTask" class="rounded-md bg-white px-4 py-2 ring-1 ring-gray-300">{{ __('projects::projects.cancel') }}</button> @endif
                </div>
            </form>
        @endif
    </section>

    <section aria-labelledby="team-heading" class="space-y-2 rounded-lg bg-white p-4 ring-1 ring-gray-200">
        <h2 id="team-heading" class="text-sm font-semibold">{{ __('projects::projects.team') }}</h2>
        @error('members') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($project->members->sortBy('name') as $member)
                <li wire:key="member-{{ $member->id }}" class="flex items-center justify-between gap-2 py-1">
                    <span>{{ $member->name }} @if ($member->pivot->role) <span class="text-xs text-gray-500">· {{ $member->pivot->role }}</span> @endif</span>
                    @if ($canManage)
                        <button type="button" wire:click="removeMember('{{ $member->id }}')" class="text-xs font-medium text-red-700 underline">{{ __('projects::projects.remove') }}<span class="sr-only">: {{ $member->name }}</span></button>
                    @endif
                </li>
            @empty
                <li class="py-1 text-gray-600">{{ __('projects::projects.no_members') }}</li>
            @endforelse
        </ul>
        @if ($canManage)
            <form wire:submit="addMember" class="flex flex-wrap items-end gap-3 text-sm" novalidate>
                <div>
                    <label for="memberId" class="block font-medium">{{ __('projects::projects.fields.member') }}</label>
                    <select id="memberId" wire:model="memberId" class="mt-1 block rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        @foreach ($candidates as $person)
                            <option wire:key="candidate-{{ $person->id }}" value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="memberRole" class="block font-medium">{{ __('projects::projects.fields.role') }}</label>
                    <input id="memberRole" type="text" maxlength="100" wire:model="memberRole" class="mt-1 block rounded-md border-gray-300 text-sm">
                </div>
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 font-semibold text-white">{{ __('projects::projects.add_member') }}</button>
            </form>
            @error('memberId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
    </section>

    <x-core::record-tags :record="$project" />

    <x-core::record-history :record="$project" :can-change="auth()->user()->can('update', $project)" />
</div>
