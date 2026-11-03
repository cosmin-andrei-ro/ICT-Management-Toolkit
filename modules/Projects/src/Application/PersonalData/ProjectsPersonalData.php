<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\PersonalData;

use Generator;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\StatusReport;
use Modules\Projects\Domain\Models\Task;

/**
 * Projects a person sponsors, manages or works in, their tasks and the status reports they wrote.
 */
final readonly class ProjectsPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        $projects = Project::query()->with('members:id')
            ->where(fn ($query) => $query->where('sponsor_id', $person->id)->orWhere('manager_id', $person->id)->orWhereHas('members', fn ($query) => $query->whereKey($person->id)))
            ->orderBy('code');

        foreach ($projects->get() as $project) {
            yield new PersonalDataEntry(__('projects::personal_data.records.project'), $project->code, $project->name, PersonalDataEntry::roles(
                $project->sponsor_id === $person->id ? __('projects::personal_data.roles.sponsor') : null,
                $project->manager_id === $person->id ? __('projects::personal_data.roles.manager') : null,
                $project->members->contains('id', $person->id) ? __('projects::personal_data.roles.member') : null,
            ), $project->created_at);
        }

        foreach (Task::query()->with('project')->where('assignee_id', $person->id)->orderBy('created_at')->get() as $task) {
            yield new PersonalDataEntry(__('projects::personal_data.records.task'), $task->project->code, $task->title, __('projects::personal_data.roles.assignee'), $task->due_on, $task->description);
        }

        foreach (StatusReport::query()->with('project')->where('author_id', $person->id)->orderBy('reported_on')->get() as $report) {
            yield new PersonalDataEntry(__('projects::personal_data.records.report'), $report->project?->code, $report->project->name ?? '', __('projects::personal_data.roles.author'), $report->reported_on,
                trim($report->achievements."\n".$report->next_steps."\n".($report->issues ?? '')));
        }
    }
}
