<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Metrics;

use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Projects\Application\BudgetFigures;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Enums\Rag;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\GateRequest;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\StatusReport;
use Modules\Projects\Domain\Models\Task;

/**
 * Indicators of the projects for the dashboards (ADR 0044, 0055).
 */
final class ProjectMetrics
{
    public static function source(): MetricSource
    {
        $view = 'projects.project.view';

        return new MetricSource('projects', 'projects::reports.metrics.group', [
            new Metric('projects.red', 'projects::reports.metrics.red', $view, warnAt: 1, alertAt: 1, route: 'projects.portfolio'),
            new Metric('projects.over_budget', 'projects::reports.metrics.over_budget', $view, warnAt: 1, route: 'projects.portfolio'),
            new Metric('projects.overdue_tasks', 'projects::reports.metrics.overdue_tasks', $view, warnAt: 1, alertAt: 20, route: 'projects.portfolio'),
            new Metric('projects.gates_waiting', 'projects::reports.metrics.gates_waiting', $view, route: 'projects.portfolio'),
        ], self::values(...));
    }

    /**
     * @return array<string, int|float|null>
     */
    public static function values(): array
    {
        $open = Project::query()->whereIn('status', [ProjectStatus::Active->value, ProjectStatus::OnHold->value])->get();
        $latest = StatusReport::query()->whereIn('project_id', $open->modelKeys())->orderByDesc('reported_on')->get()->unique('project_id');

        return [
            'projects.red' => $latest->filter(fn (StatusReport $report): bool => $report->overall === Rag::Red)->count(),
            'projects.over_budget' => $open->filter(fn (Project $project): bool => BudgetFigures::of($project)->isOver())->count(),
            'projects.overdue_tasks' => Task::query()->whereIn('project_id', $open->modelKeys())->where('status', '!=', TaskStatus::Done->value)
                ->whereNotNull('due_on')->where('due_on', '<', today()->toDateString())->count(),
            'projects.gates_waiting' => GateRequest::query()->where('status', 'pending')->count(),
        ];
    }
}
