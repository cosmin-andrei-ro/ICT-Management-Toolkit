<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\Task;

/**
 * Project managers run every project; IT staff with the view permission read
 * them all; anyone else sees the projects they sponsor, run or work on. A
 * project's own manager changes it; team members move their own tasks.
 * Closed and cancelled projects are kept as they ended.
 */
final readonly class ProjectPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('projects.project.view') || $user->can('projects.project.manage')
            || Project::query()->involving($user)->exists();
    }

    public function view(User $user, Project $project): bool
    {
        return $this->inCurrentOrganization($project)
            && ($user->can('projects.project.view') || $user->can('projects.project.manage') || $project->involves($user));
    }

    public function create(User $user): bool
    {
        return $user->can('projects.project.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->inCurrentOrganization($project) && $project->isOpen()
            && ($user->can('projects.project.manage') || $project->manager_id === $user->id);
    }

    /**
     * Moving a task on the board: who runs the project, or the person it is assigned to.
     */
    public function workOn(User $user, Project $project, Task $task): bool
    {
        return $task->project_id === $project->id
            && ($this->update($user, $project) || ($this->inCurrentOrganization($project) && $project->isOpen() && $task->assignee_id === $user->id));
    }

    private function inCurrentOrganization(Project $project): bool
    {
        return $this->context->has() && $project->organization_id === $this->context->id();
    }
}
