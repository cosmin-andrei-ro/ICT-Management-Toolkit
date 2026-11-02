<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Application\Actions\ManageTasks;
use Modules\Projects\Application\Actions\ManageTeam;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\Phase;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\Task;

/**
 * A project: its phases, its team, and its tasks as a list or as a board.
 *
 * @property-read Project $project Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowProject extends Component
{
    #[Locked]
    public string $projectId = '';

    /** "board" or "list". */
    #[Url(as: 'view')]
    public string $display = 'board';

    #[Url]
    public string $phase = '';

    #[Locked]
    public ?string $editingPhaseId = null;

    public string $phaseStart = '';

    public string $phaseEnd = '';

    public string $memberId = '';

    public string $memberRole = '';

    #[Locked]
    public ?string $editingTaskId = null;

    public string $taskTitle = '';

    public string $taskDescription = '';

    public string $taskPhaseId = '';

    public string $taskAssigneeId = '';

    public string $taskDueOn = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->projectId = $project->id;
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->with(['sponsor', 'manager', 'members', 'phases'])->findOrFail($this->projectId);
    }

    public function showAs(string $display): void
    {
        $this->display = in_array($display, ['board', 'list'], true) ? $display : 'board';
    }

    public function editPhase(string $phaseId): void
    {
        $this->authorize('update', $this->project);
        $phase = $this->phaseOf($phaseId);
        $this->editingPhaseId = $phase->id;
        $this->phaseStart = $phase->planned_start?->toDateString() ?? '';
        $this->phaseEnd = $phase->planned_end?->toDateString() ?? '';
    }

    public function savePhase(ManageTeam $team): void
    {
        $this->authorize('update', $this->project);
        $this->resetErrorBag();
        $this->validate(['phaseStart' => ['nullable', 'date'], 'phaseEnd' => ['nullable', 'date']]);
        $team->planPhase($this->phaseOf((string) $this->editingPhaseId), $this->phaseStart ?: null, $this->phaseEnd ?: null);
        $this->reset('editingPhaseId', 'phaseStart', 'phaseEnd');
        unset($this->project);
    }

    public function addMember(ManageTeam $team): void
    {
        $this->authorize('update', $this->project);
        $this->resetErrorBag();
        $this->validate(['memberId' => ['required', 'uuid'], 'memberRole' => ['nullable', 'string', 'max:100']]);
        $team->add($this->project, $this->memberId, trim($this->memberRole) !== '' ? trim($this->memberRole) : null);
        $this->reset('memberId', 'memberRole');
        unset($this->project);
    }

    public function removeMember(string $userId, ManageTeam $team): void
    {
        $this->authorize('update', $this->project);
        $this->resetErrorBag();
        $team->remove($this->project, $userId);
        unset($this->project);
    }

    public function editTask(string $taskId): void
    {
        $this->authorize('update', $this->project);
        $task = $this->taskOf($taskId);
        $this->resetErrorBag();
        $this->editingTaskId = $task->id;
        $this->taskTitle = $task->title;
        $this->taskDescription = $task->description ?? '';
        $this->taskPhaseId = $task->phase_id;
        $this->taskAssigneeId = $task->assignee_id ?? '';
        $this->taskDueOn = $task->due_on?->toDateString() ?? '';
    }

    public function cancelTask(): void
    {
        $this->resetErrorBag();
        $this->reset('editingTaskId', 'taskTitle', 'taskDescription', 'taskPhaseId', 'taskAssigneeId', 'taskDueOn');
    }

    public function saveTask(ManageTasks $tasks): void
    {
        $this->authorize('update', $this->project);
        $this->resetErrorBag();
        $this->validate([
            'taskTitle' => ['required', 'string', 'max:200'],
            'taskDescription' => ['nullable', 'string', 'max:5000'],
            'taskPhaseId' => ['required', 'uuid'],
            'taskAssigneeId' => ['nullable', 'uuid'],
            'taskDueOn' => ['nullable', 'date'],
        ]);

        $tasks->save($this->project, $this->editingTaskId !== null ? $this->taskOf($this->editingTaskId) : null, [
            'phase_id' => $this->taskPhaseId,
            'title' => trim($this->taskTitle),
            'description' => trim($this->taskDescription) !== '' ? trim($this->taskDescription) : null,
            'assignee_id' => $this->taskAssigneeId !== '' ? $this->taskAssigneeId : null,
            'due_on' => $this->taskDueOn !== '' ? $this->taskDueOn : null,
        ]);
        $this->cancelTask();
    }

    /**
     * Moves a task one column left (-1) or right (1) on the board.
     */
    public function moveTask(string $taskId, int $direction, ManageTasks $tasks): void
    {
        $task = $this->taskOf($taskId);
        $this->authorize('workOn', [$task->project, $task]);
        $target = $task->status->neighbour($direction > 0 ? 1 : -1);

        if ($target !== null) {
            $tasks->move($task, $target);
        }
    }

    public function deleteTask(string $taskId, ManageTasks $tasks): void
    {
        $this->authorize('update', $this->project);
        $tasks->delete($this->taskOf($taskId));
        $this->cancelTask();
    }

    public function render(OrganizationContext $context): View
    {
        $project = $this->project;
        $user = $this->actor();
        $tasks = $project->tasks()->with(['assignee', 'phase'])
            ->when($this->phase !== '', fn ($query) => $query->where('phase_id', $this->phase))
            ->orderBy('position')->get()
            ->each(fn (Task $task) => $task->setRelation('project', $project));
        $team = collect([$project->manager, $project->sponsor, ...$project->members])->filter()->unique('id')->sortBy('name')->values();

        return view('projects::livewire.show', [
            'project' => $project,
            'tasks' => $tasks,
            'columns' => TaskStatus::cases(),
            'team' => $team,
            'canManage' => $user->can('update', $project),
            'user' => $user,
            'candidates' => $user->can('update', $project)
                ? User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))
                    ->whereNotIn('id', $project->members->modelKeys())->orderBy('name')->get(['id', 'name'])
                : collect(),
        ])->title($project->code.' '.$project->name);
    }

    private function phaseOf(string $phaseId): Phase
    {
        return Phase::query()->where('project_id', $this->projectId)->findOrFail($phaseId);
    }

    private function taskOf(string $taskId): Task
    {
        return Task::query()->with('project')->where('project_id', $this->projectId)->findOrFail($taskId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
