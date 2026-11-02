<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Domain\Models\Phase;
use Modules\Projects\Domain\Models\Project;

/**
 * The team of a project, and the planned dates of its phases.
 */
final readonly class ManageTeam
{
    public function __construct(private OrganizationContext $context) {}

    public function add(Project $project, string $userId, ?string $role): void
    {
        if (! SaveProject::isMember($userId, $this->context->requireId())) {
            throw ValidationException::withMessages(['memberId' => __('projects::projects.errors.person')]);
        }

        if ($project->members()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['memberId' => __('projects::projects.errors.already_member')]);
        }

        $project->members()->attach($userId, ['role' => $role]);
    }

    public function remove(Project $project, string $userId): void
    {
        if ($project->tasks()->where('assignee_id', $userId)->where('status', '!=', 'done')->exists()) {
            throw ValidationException::withMessages(['members' => __('projects::projects.errors.member_has_tasks')]);
        }

        $project->members()->detach($userId);
    }

    public function planPhase(Phase $phase, ?string $start, ?string $end): void
    {
        if ($start !== null && $end !== null && $end < $start) {
            throw ValidationException::withMessages(['phaseDates' => __('projects::projects.errors.dates')]);
        }

        $phase->forceFill(['planned_start' => $start, 'planned_end' => $end])->save();
    }
}
