<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Core\Application\Pdf\PdfDownload;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\BudgetFigures;
use Modules\Projects\Application\ClosureReadiness;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\Project;
use Symfony\Component\HttpFoundation\Response;

/**
 * A project's status report on paper (ADR 0055): phases, latest status,
 * budget, deliverables and what blocks closure, overdue tasks and gates -
 * to print, or as a PDF recorded as an export.
 */
final readonly class ProjectReportController
{
    public function __construct(
        private Gate $gate,
        private PdfDownload $pdf,
        private ClosureReadiness $readiness,
    ) {}

    public function show(Project $project): View
    {
        $this->gate->authorize('view', $project);

        return view('projects::print.report', [...$this->data($project), 'pdf' => false, 'pdfAvailable' => $this->pdf->isAvailable()]);
    }

    public function pdf(Project $project, Request $request): Response
    {
        $this->gate->authorize('view', $project);
        $html = view('projects::print.report', [...$this->data($project), 'pdf' => true, 'pdfAvailable' => true, 'styles' => $this->pdf->stylesheet()])->render();
        $user = $request->user();

        return $this->pdf->respond($html, strtolower($project->code).'-status', route('projects.report', $project), $project, ['report' => 'status'], $user instanceof User ? $user : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Project $project): array
    {
        $project->load(['manager', 'sponsor', 'phases', 'members']);

        return [
            'project' => $project,
            'report' => $project->latestReport(),
            'budget' => BudgetFigures::of($project),
            'deliverables' => $project->deliverables()->with('phase')->get()->sortBy(fn ($deliverable): int => $deliverable->phase->position)->values(),
            'checklist' => $this->readiness->checklist($project),
            'blockers' => $project->isOpen() ? $this->readiness->blockers($project) : [],
            'overdue' => $project->tasks()->with(['assignee', 'phase'])->where('status', '!=', TaskStatus::Done->value)->whereNotNull('due_on')->where('due_on', '<', today()->toDateString())->orderBy('due_on')->get(),
            'tasks' => ['total' => $project->tasks()->count(), 'done' => $project->tasks()->where('status', TaskStatus::Done->value)->count()],
            'gates' => $project->gateRequests()->with(['decider', 'fromPhase', 'toPhase'])->where('status', '!=', 'pending')->limit(10)->get(),
            'pending' => $project->pendingGate(),
        ];
    }
}
