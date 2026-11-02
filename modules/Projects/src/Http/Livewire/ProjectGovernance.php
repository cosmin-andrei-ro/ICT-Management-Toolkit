<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\Actions\StageGates;
use Modules\Projects\Application\Actions\WriteStatusReport;
use Modules\Projects\Domain\Enums\Rag;
use Modules\Projects\Domain\Models\Project;

/**
 * On a project's page: the stage gate - asked by the manager, decided by the
 * sponsor - and the status reports.
 */
final class ProjectGovernance extends Component
{
    #[Locked]
    public string $projectId = '';

    public string $gateNote = '';

    public string $decisionNote = '';

    public bool $writing = false;

    public string $overall = 'green';

    public string $schedule = 'green';

    public string $budget = 'green';

    public string $scope = 'green';

    public string $achievements = '';

    public string $nextSteps = '';

    public string $issues = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->projectId = $project->id;
    }

    public function requestGate(StageGates $gates): void
    {
        $project = $this->project();
        $this->authorize('update', $project);
        $this->resetErrorBag();
        $this->validate(['gateNote' => ['nullable', 'string', 'max:5000']]);
        $gates->request($project, $this->actor(), trim($this->gateNote) !== '' ? trim($this->gateNote) : null);
        $this->reset('gateNote');
        $this->dispatch('project-changed');
    }

    public function approve(StageGates $gates): void
    {
        $this->decide(true, $gates);
    }

    public function reject(StageGates $gates): void
    {
        $this->decide(false, $gates);
    }

    private function decide(bool $approve, StageGates $gates): void
    {
        $project = $this->project();
        $this->authorize('decideGate', $project);
        $this->resetErrorBag();
        $this->validate(['decisionNote' => ['nullable', 'string', 'max:5000']]);
        $gate = $project->pendingGate() ?? abort(404);
        $gates->decide($gate, $this->actor(), $approve, trim($this->decisionNote) !== '' ? trim($this->decisionNote) : null);
        $this->reset('decisionNote');
        $this->dispatch('project-changed');
    }

    public function startReport(): void
    {
        $project = $this->project();
        $this->authorize('update', $project);
        $last = $project->latestReport();
        $this->writing = true;

        if ($last !== null) {
            $this->overall = $last->overall->value;
            $this->schedule = $last->schedule->value;
            $this->budget = $last->budget->value;
            $this->scope = $last->scope->value;
        }
    }

    public function saveReport(WriteStatusReport $write): void
    {
        $project = $this->project();
        $this->authorize('update', $project);
        $this->resetErrorBag();
        $rag = ['required', Rule::enum(Rag::class)];
        $this->validate([
            'overall' => $rag, 'schedule' => $rag, 'budget' => $rag, 'scope' => $rag,
            'achievements' => ['required', 'string', 'max:5000'],
            'nextSteps' => ['required', 'string', 'max:5000'],
            'issues' => ['nullable', 'string', 'max:5000'],
        ]);

        $write->handle($project, $this->actor(), [
            'overall' => Rag::from($this->overall),
            'schedule' => Rag::from($this->schedule),
            'budget' => Rag::from($this->budget),
            'scope' => Rag::from($this->scope),
            'achievements' => trim($this->achievements),
            'next_steps' => trim($this->nextSteps),
            'issues' => trim($this->issues) !== '' ? trim($this->issues) : null,
        ]);
        $this->reset('writing', 'achievements', 'nextSteps', 'issues');
    }

    public function cancelReport(): void
    {
        $this->resetErrorBag();
        $this->reset('writing', 'achievements', 'nextSteps', 'issues');
    }

    public function render(): View
    {
        $project = $this->project();
        $user = $this->actor();

        return view('projects::livewire.governance', [
            'project' => $project,
            'current' => $project->currentPhase(),
            'next' => ($current = $project->currentPhase()) !== null ? $project->phases->firstWhere('position', $current->position + 1) : null,
            'pending' => $project->pendingGate()?->load(['requester', 'fromPhase', 'toPhase']),
            'history' => $project->gateRequests()->with(['requester', 'decider', 'fromPhase', 'toPhase'])->where('status', '!=', 'pending')->limit(10)->get(),
            'reports' => $project->statusReports()->with('author')->limit(10)->get(),
            'canManage' => $user->can('update', $project),
            'isSponsor' => $user->can('decideGate', $project),
            'user' => $user,
            'ragOptions' => Rag::cases(),
            'everyDays' => config()->integer('projects.reports.every_days', 14),
        ]);
    }

    private function project(): Project
    {
        return Project::query()->with(['phases', 'sponsor', 'manager'])->findOrFail($this->projectId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
