@props(['task', 'canManage', 'user'])
@php($canMove = $user->can('workOn', [$task->project, $task]))
<div class="space-y-1 rounded-md bg-white p-2 text-sm shadow-sm ring-1 ring-gray-200">
    <p class="font-medium">{{ $task->title }}</p>
    <p class="text-xs text-gray-600">
        {{ $task->phase->key->label() }}
        · {{ $task->assignee?->name ?? __('projects::projects.unassigned') }}
        @if ($task->due_on) · <span @class(['font-medium text-red-700' => $task->isOverdue()])>{{ $task->due_on->toDateString() }}@if ($task->isOverdue()) ({{ __('projects::projects.overdue') }})@endif</span> @endif
    </p>
    @if ($task->description) <p class="whitespace-pre-line text-xs text-gray-700">{{ $task->description }}</p> @endif
    <div class="flex flex-wrap items-center gap-2 pt-1 text-xs">
        @if ($canMove)
            @if ($task->status->neighbour(-1))
                <button type="button" wire:click="moveTask('{{ $task->id }}', -1)" class="rounded px-1.5 py-0.5 ring-1 ring-gray-300">← {{ $task->status->neighbour(-1)->label() }}<span class="sr-only">: {{ $task->title }}</span></button>
            @endif
            @if ($task->status->neighbour(1))
                <button type="button" wire:click="moveTask('{{ $task->id }}', 1)" class="rounded px-1.5 py-0.5 ring-1 ring-gray-300">{{ $task->status->neighbour(1)->label() }} →<span class="sr-only">: {{ $task->title }}</span></button>
            @endif
        @endif
        @if ($canManage)
            <button type="button" wire:click="editTask('{{ $task->id }}')" class="font-medium text-blue-700 underline">{{ __('projects::projects.edit_task') }}<span class="sr-only">: {{ $task->title }}</span></button>
            <button type="button" wire:click="deleteTask('{{ $task->id }}')" wire:confirm="{{ __('projects::projects.delete_task_confirm') }}" class="font-medium text-red-700 underline">{{ __('projects::projects.delete') }}<span class="sr-only">: {{ $task->title }}</span></button>
        @endif
    </div>
</div>
