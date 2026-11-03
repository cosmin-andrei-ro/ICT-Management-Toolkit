<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Api\ApiQuery;
use Modules\Projects\Application\BudgetFigures;
use Modules\Projects\Domain\Models\Phase;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\Task;

/**
 * Projects, read-only (ADR 0065): the portfolio, and each project's phases,
 * tasks, latest status report and budget.
 */
final readonly class ProjectController
{
    public function __construct(private Gate $gate) {}

    /**
     * List the projects the user may see (all with portfolio access, otherwise those they take part in).
     * Filters: `filter[status]`, `filter[kind]`. Sorts: `code`, `planned_end`, `updated_at`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Project::class);
        $user = $this->user($request);
        $all = $user->can('projects.project.view') || $user->can('projects.project.manage');

        return ApiQuery::collection(ApiQuery::paginate($request, Project::query()
            ->unless($all, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('sponsor_id', $user->id)->orWhere('manager_id', $user->id)->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id)))), [
                'status' => fn (Builder $query, string $value) => $query->where('status', $value),
                'kind' => fn (Builder $query, string $value) => $query->where('kind', $value),
            ], ['code' => 'code', 'planned_end' => 'planned_end', 'updated_at' => 'updated_at'], 'code'), fn (Project $project): array => self::summary($project));
    }

    /**
     * Show a project with its phases, tasks and budget.
     */
    public function show(Project $project): JsonResponse
    {
        $this->gate->authorize('view', $project);
        $project->load(['phases', 'tasks']);
        $budget = BudgetFigures::of($project);

        return response()->json(['data' => [
            ...self::summary($project),
            'description' => $project->description,
            'phases' => $project->phases->sortBy('position')->values()->map(fn (Phase $phase): array => [
                'key' => $phase->key->value, 'status' => $phase->status->value, 'planned_start' => $phase->planned_start?->toDateString(), 'planned_end' => $phase->planned_end?->toDateString(),
                'started_at' => $phase->started_at?->toIso8601String(), 'completed_at' => $phase->completed_at?->toIso8601String(),
            ])->all(),
            'tasks' => $project->tasks->map(fn (Task $task): array => [
                'id' => $task->id, 'phase_id' => $task->phase_id, 'title' => $task->title, 'assignee_id' => $task->assignee_id, 'due_on' => $task->due_on?->toDateString(), 'status' => $task->status->value,
            ])->all(),
            'budget' => ['amount' => $budget->budget, 'spent' => $budget->spent, 'remaining' => $budget->remaining, 'percent' => $budget->percent, 'currency' => $budget->currency, 'over' => $budget->isOver(), 'by_category' => $budget->byCategory],
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(Project $project): array
    {
        $report = $project->latestReport();

        return [
            'id' => $project->id, 'code' => $project->code, 'name' => $project->name, 'kind' => $project->kind->value, 'status' => $project->status->value,
            'sponsor_id' => $project->sponsor_id, 'manager_id' => $project->manager_id, 'application_id' => $project->application_id,
            'phase' => $project->currentPhase()?->key->value, 'planned_start' => $project->planned_start?->toDateString(), 'planned_end' => $project->planned_end?->toDateString(),
            'latest_report' => $report !== null ? ['reported_on' => $report->reported_on->toDateString(), 'overall' => $report->overall->value, 'schedule' => $report->schedule->value, 'budget' => $report->budget->value, 'scope' => $report->scope->value] : null,
            'closed_at' => $project->closed_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
