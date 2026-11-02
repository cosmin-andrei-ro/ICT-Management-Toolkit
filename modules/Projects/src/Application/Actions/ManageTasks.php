<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\Task;

/**
 * Tasks of a project: given to someone on the team, in a phase of the
 * project, moved across the board.
 */
final readonly class ManageTasks
{
    /**
     * @param  array{phase_id: string, title: string, description: ?string, assignee_id: ?string, due_on: ?string}  $data
     */
    public function save(Project $project, ?Task $task, array $data): Task
    {
        if (! $project->phases()->whereKey($data['phase_id'])->exists()) {
            throw ValidationException::withMessages(['taskPhaseId' => __('projects::projects.errors.phase')]);
        }

        if ($data['assignee_id'] !== null && ! $this->onTeam($project, $data['assignee_id'])) {
            throw ValidationException::withMessages(['taskAssigneeId' => __('projects::projects.errors.not_on_team')]);
        }

        $creating = $task === null;
        $task ??= new Task;
        $task->forceFill([...$data, 'project_id' => $project->id]);

        if ($creating) {
            $task->forceFill(['position' => $this->lastPosition($project, TaskStatus::Todo) + 1]);
        }

        $task->save();

        return $task;
    }

    public function move(Task $task, TaskStatus $status): void
    {
        if ($task->status === $status) {
            return;
        }

        $task->forceFill([
            'status' => $status,
            'position' => $this->lastPosition($task->project, $status) + 1,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ])->save();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * The manager, the sponsor and the members.
     */
    public function onTeam(Project $project, string $userId): bool
    {
        return in_array($userId, array_filter([$project->manager_id, $project->sponsor_id]), true)
            || $project->members()->whereKey($userId)->exists();
    }

    private function lastPosition(Project $project, TaskStatus $status): int
    {
        $max = $project->tasks()->where('status', $status->value)->max('position');

        return is_numeric($max) ? (int) $max : 0;
    }
}
