<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;
use Modules\Projects\Application\BudgetFigures;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\Cost;
use Modules\Projects\Domain\Models\Deliverable;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\StatusReport;

/**
 * The project portfolio in a workbook (ADR 0055): every project with its
 * phase, latest status and budget; costs, deliverables and status reports.
 */
final readonly class PortfolioExport implements ExportSource
{
    public const string TYPE = 'projects.portfolio';

    public function sheets(Export $export): array
    {
        return [
            new ExportSheet(__('projects::reports.export.projects'), [
                __('projects::reports.export.code'), __('projects::projects.fields.name'), __('projects::projects.fields.kind'), __('projects::projects.fields.status'), __('projects::projects.fields.phase'),
                __('projects::projects.fields.manager'), __('projects::projects.fields.sponsor'), __('projects::projects.fields.planned_start'), __('projects::projects.fields.planned_end'),
                __('projects::reports.export.latest_rag'), __('projects::reports.export.reported_on'), __('projects::budget.currency'), __('projects::budget.approved'), __('projects::budget.spent'), __('projects::budget.remaining'),
                __('projects::reports.export.tasks'), __('projects::reports.export.tasks_done'), __('projects::reports.export.tasks_overdue'),
            ], $this->projectRows()),
            new ExportSheet(__('projects::reports.export.costs'), [
                __('projects::reports.export.code'), __('projects::budget.fields.incurred_on'), __('projects::budget.fields.category'), __('projects::budget.fields.description'),
                __('projects::budget.fields.amount'), __('projects::budget.currency'), __('projects::budget.fields.supplier'), __('projects::budget.fields.invoice'),
            ], $this->costRows()),
            new ExportSheet(__('projects::deliverables.heading'), [
                __('projects::reports.export.code'), __('projects::projects.fields.phase'), __('projects::deliverables.title'), __('projects::deliverables.required'), __('projects::reports.export.linked'), __('projects::reports.export.delivered_on'),
            ], $this->deliverableRows()),
            new ExportSheet(__('projects::governance.reports_heading'), [
                __('projects::reports.export.code'), __('projects::reports.export.reported_on'), __('projects::governance.dimensions.overall'), __('projects::governance.dimensions.schedule'),
                __('projects::governance.dimensions.budget'), __('projects::governance.dimensions.scope'), __('projects::governance.fields.achievements'), __('projects::governance.fields.nextSteps'), __('projects::governance.fields.issues'),
            ], $this->reportRows()),
        ];
    }

    public function filename(Export $export): string
    {
        return 'projects-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function projectRows(): Generator
    {
        foreach (Project::query()->with(['manager', 'sponsor', 'phases'])->withCount(['tasks', 'tasks as done_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done->value),
            'tasks as overdue_count' => fn (Builder $query) => $query->where('status', '!=', TaskStatus::Done->value)->whereNotNull('due_on')->where('due_on', '<', today()->toDateString())])
            ->orderBy('code')->get() as $project) {
            $report = $project->latestReport();
            $budget = BudgetFigures::of($project);

            yield [
                $project->code, $project->name, $project->kind->label(), $project->status->label(), $project->currentPhase()?->key->label(),
                $project->manager?->name, $project->sponsor?->name, $project->planned_start?->toDateString(), $project->planned_end?->toDateString(),
                $report?->overall->label(), $report?->reported_on->toDateString(), $budget->currency, $budget->budget, $budget->spent, $budget->remaining,
                self::count($project, 'tasks_count'), self::count($project, 'done_count'), self::count($project, 'overdue_count'),
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function costRows(): Generator
    {
        $codes = self::codes();
        $currencies = [];

        foreach (Project::query()->get(['id', 'currency']) as $project) {
            $currencies[$project->id] = $project->currency;
        }

        foreach (Cost::query()->with('supplier')->orderBy('incurred_on')->lazyById(500) as $cost) {
            yield [
                $codes[$cost->project_id] ?? null, $cost->incurred_on->toDateString(), $cost->category->label(), $cost->description,
                (float) $cost->amount, $currencies[$cost->project_id] ?? null, $cost->supplier?->name, $cost->invoice_number,
            ];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function deliverableRows(): Generator
    {
        $codes = self::codes();

        foreach (Deliverable::query()->with('phase')->lazyById(500) as $deliverable) {
            yield [$codes[$deliverable->project_id] ?? null, $deliverable->phase->key->label(), $deliverable->title, $deliverable->required, $deliverable->linked_title, $deliverable->delivered_at?->toDateString()];
        }
    }

    /**
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function reportRows(): Generator
    {
        $codes = self::codes();

        foreach (StatusReport::query()->orderBy('reported_on')->lazyById(500) as $report) {
            yield [
                $codes[$report->project_id] ?? null, $report->reported_on->toDateString(), $report->overall->label(), $report->schedule->label(),
                $report->budget->label(), $report->scope->label(), $report->achievements, $report->next_steps, $report->issues,
            ];
        }
    }

    /**
     * @return array<string, string> Project code by id.
     */
    private static function codes(): array
    {
        $codes = [];

        foreach (Project::query()->get(['id', 'code']) as $project) {
            $codes[$project->id] = $project->code;
        }

        return $codes;
    }

    private static function count(Project $project, string $attribute): int
    {
        $value = $project->getAttribute($attribute);

        return is_numeric($value) ? (int) $value : 0;
    }
}
