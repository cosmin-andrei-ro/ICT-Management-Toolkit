<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Application\Actions\SaveProject;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Models\Project;

#[Layout('core::components.layouts.app')]
final class ProjectForm extends Component
{
    #[Locked]
    public ?string $projectId = null;

    public string $name = '';

    public string $description = '';

    public string $kind = 'general';

    public string $sponsorId = '';

    public string $managerId = '';

    public string $plannedStart = '';

    public string $plannedEnd = '';

    public function mount(?Project $project = null): void
    {
        if ($project === null || ! $project->exists) {
            $this->authorize('create', Project::class);
            $this->managerId = (string) auth()->id();

            return;
        }

        $this->authorize('update', $project);
        $this->projectId = $project->id;
        $this->name = $project->name;
        $this->description = $project->description ?? '';
        $this->kind = $project->kind->value;
        $this->sponsorId = $project->sponsor_id ?? '';
        $this->managerId = $project->manager_id ?? '';
        $this->plannedStart = $project->planned_start?->toDateString() ?? '';
        $this->plannedEnd = $project->planned_end?->toDateString() ?? '';
    }

    public function save(SaveProject $save): void
    {
        $project = $this->projectId !== null ? Project::query()->findOrFail($this->projectId) : null;
        $this->authorize($project !== null ? 'update' : 'create', $project ?? Project::class);
        $this->resetErrorBag();

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'kind' => ['required', Rule::enum(ProjectKind::class)],
            'sponsorId' => ['nullable', 'uuid'],
            'managerId' => ['required', 'uuid'],
            'plannedStart' => ['nullable', 'date'],
            'plannedEnd' => ['nullable', 'date', 'after_or_equal:plannedStart'],
        ]);

        $project = $save->handle($project, [
            'name' => trim($this->name),
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'kind' => ProjectKind::from($this->kind),
            'sponsor_id' => $this->sponsorId !== '' ? $this->sponsorId : null,
            'manager_id' => $this->managerId,
            'planned_start' => $this->plannedStart !== '' ? $this->plannedStart : null,
            'planned_end' => $this->plannedEnd !== '' ? $this->plannedEnd : null,
        ]);

        $this->redirectRoute('projects.show', $project);
    }

    public function render(OrganizationContext $context): View
    {
        return view('projects::livewire.form', [
            'people' => User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']),
            'kinds' => ProjectKind::cases(),
        ])->title(__($this->projectId !== null ? 'projects::projects.edit' : 'projects::projects.new'));
    }
}
