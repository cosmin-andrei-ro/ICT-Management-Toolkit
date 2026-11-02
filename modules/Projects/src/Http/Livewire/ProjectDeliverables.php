<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\Actions\ManageDeliverables;
use Modules\Projects\Application\ClosureReadiness;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Models\Deliverable;
use Modules\Projects\Domain\Models\Project;

/**
 * On a project's page: what it delivers, the records those deliverables
 * became, and - for a new application - the closure checklist.
 */
final class ProjectDeliverables extends Component
{
    #[Locked]
    public string $projectId = '';

    public string $deliverableTitle = '';

    public string $deliverablePhaseId = '';

    public bool $deliverableRequired = true;

    #[Locked]
    public ?string $linkingId = null;

    public string $linkType = '';

    public string $linkTerm = '';

    public string $applicationTerm = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->projectId = $project->id;
    }

    public function add(ManageDeliverables $manage): void
    {
        $project = $this->project();
        $this->authorize('update', $project);
        $this->resetErrorBag();
        $this->validate([
            'deliverableTitle' => ['required', 'string', 'max:200'],
            'deliverablePhaseId' => ['required', 'uuid'],
            'deliverableRequired' => ['boolean'],
        ]);
        $manage->add($project, ['phase_id' => $this->deliverablePhaseId, 'title' => trim($this->deliverableTitle), 'required' => $this->deliverableRequired]);
        $this->reset('deliverableTitle', 'deliverableRequired');
    }

    public function startLinking(string $deliverableId): void
    {
        $this->authorize('update', $this->project());
        $this->resetErrorBag();
        $this->linkingId = $this->deliverable($deliverableId)->id;
        $this->reset('linkType', 'linkTerm');
    }

    public function cancelLinking(): void
    {
        $this->reset('linkingId', 'linkType', 'linkTerm');
    }

    public function link(string $recordId, ManageDeliverables $manage): void
    {
        $this->authorize('update', $this->project());
        $manage->link($this->deliverable((string) $this->linkingId), $this->linkType, $recordId, $this->actor());
        $this->cancelLinking();
    }

    public function unlink(string $deliverableId, ManageDeliverables $manage): void
    {
        $this->authorize('update', $this->project());
        $manage->unlink($this->deliverable($deliverableId));
    }

    public function deliver(string $deliverableId, ManageDeliverables $manage): void
    {
        $this->authorize('update', $this->project());
        $manage->deliver($this->deliverable($deliverableId), $this->actor(), true);
    }

    public function undeliver(string $deliverableId, ManageDeliverables $manage): void
    {
        $this->authorize('update', $this->project());
        $manage->deliver($this->deliverable($deliverableId), $this->actor(), false);
    }

    public function delete(string $deliverableId, ManageDeliverables $manage): void
    {
        $this->authorize('update', $this->project());
        $manage->delete($this->deliverable($deliverableId));
    }

    public function chooseApplication(string $applicationId, ManageDeliverables $manage): void
    {
        $this->authorize('update', $this->project());
        $this->resetErrorBag();
        $manage->chooseApplication($this->project(), $applicationId);
        $this->reset('applicationTerm');
    }

    public function render(LinkedRecords $records, Applications $applications, ClosureReadiness $readiness): View
    {
        $project = $this->project();
        $user = $this->actor();
        $deliverables = $project->deliverables()->with(['phase', 'deliverer'])->orderBy('created_at')->get()
            ->sortBy(fn (Deliverable $deliverable): int => $deliverable->phase->position)->values();

        // Records the viewer may open, by type and id; others keep their stored title, without a link.
        $resolved = [];

        foreach ($deliverables->whereNotNull('linked_type')->groupBy('linked_type') as $type => $group) {
            $ids = array_values(array_filter($group->map(fn (Deliverable $deliverable): ?string => $deliverable->linked_id)->all(), is_string(...)));

            foreach ($records->resolve((string) $type, $ids, $user) as $record) {
                $resolved[$type.':'.$record->id] = $record;
            }
        }

        $isApplication = $project->kind === ProjectKind::Application;

        return view('projects::livewire.deliverables', [
            'project' => $project,
            'deliverables' => $deliverables,
            'resolved' => $resolved,
            'kinds' => $records->searchable(),
            'candidates' => $this->linkingId !== null && $this->linkType !== '' && array_key_exists($this->linkType, $records->searchable())
                ? $records->search($this->linkType, trim($this->linkTerm), $user) : [],
            'canManage' => $user->can('update', $project),
            'checklist' => $readiness->checklist($project),
            'blockers' => $project->isOpen() ? $readiness->blockers($project) : [],
            'isApplication' => $isApplication,
            'application' => $isApplication && $project->application_id !== null ? ($applications->find([$project->application_id])[$project->application_id] ?? null) : null,
            'applicationCandidates' => $isApplication && mb_strlen(trim($this->applicationTerm)) >= 2 ? $applications->search(trim($this->applicationTerm), 8) : [],
        ]);
    }

    private function project(): Project
    {
        return Project::query()->with('phases')->findOrFail($this->projectId);
    }

    private function deliverable(string $deliverableId): Deliverable
    {
        return Deliverable::query()->where('project_id', $this->projectId)->findOrFail($deliverableId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
