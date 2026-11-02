<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Domain\Enums\Rag;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\StatusReport;

/**
 * The project manager's status report of the day; writing again the same
 * day replaces it.
 */
final readonly class WriteStatusReport
{
    /**
     * @param  array{overall: Rag, schedule: Rag, budget: Rag, scope: Rag, achievements: string, next_steps: string, issues: ?string}  $data
     */
    public function handle(Project $project, User $author, array $data, ?CarbonImmutable $today = null): StatusReport
    {
        $today ??= CarbonImmutable::today();
        $report = StatusReport::query()->where('project_id', $project->id)->whereDate('reported_on', $today->toDateString())->first() ?? new StatusReport;
        $report->forceFill([...$data, 'project_id' => $project->id, 'reported_on' => $today->toDateString(), 'author_id' => $author->id])->save();

        return $report;
    }
}
