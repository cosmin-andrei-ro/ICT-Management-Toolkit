<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\Actions\ManageTasks;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\GateRequest;
use Modules\Projects\Domain\Models\Task;

/**
 * A person's unfinished tasks across every open project.
 */
#[Layout('core::components.layouts.app')]
final class MyTasks extends Component
{
    public function moveTask(string $taskId, int $direction, ManageTasks $tasks): void
    {
        $task = Task::query()->with('project')->where('assignee_id', $this->actor()->id)->findOrFail($taskId);
        $this->authorize('workOn', [$task->project, $task]);
        $target = $task->status->neighbour($direction > 0 ? 1 : -1);

        if ($target !== null) {
            $tasks->move($task, $target);
        }
    }

    public function render(): View
    {
        $tasks = Task::query()->with(['project', 'phase'])
            ->where('assignee_id', $this->actor()->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereHas('project', fn ($query) => $query->whereIn('status', [ProjectStatus::Active->value, ProjectStatus::OnHold->value]))
            ->orderByRaw('due_on NULLS LAST')->orderBy('position')
            ->get()
            ->groupBy('project_id');

        $decisions = GateRequest::query()->with(['project', 'toPhase', 'requester'])
            ->where('status', 'pending')
            ->whereHas('project', fn ($query) => $query->where('sponsor_id', $this->actor()->id))
            ->get();

        return view('projects::livewire.my-tasks', ['groups' => $tasks, 'decisions' => $decisions])->title(__('projects::projects.my_tasks'));
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
