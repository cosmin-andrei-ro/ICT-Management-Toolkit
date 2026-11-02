<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Models\Deliverable;
use Modules\Projects\Domain\Models\Project;

/**
 * Deliverables of a project, the records they became, and the application a
 * new-application project brings in. Links are checked against what the
 * person may see in the other module.
 */
final readonly class ManageDeliverables
{
    public function __construct(private LinkedRecords $records, private Applications $applications) {}

    /**
     * @param  array{phase_id: string, title: string, required: bool}  $data
     */
    public function add(Project $project, array $data): Deliverable
    {
        if (! $project->phases()->whereKey($data['phase_id'])->exists()) {
            throw ValidationException::withMessages(['deliverablePhaseId' => __('projects::projects.errors.phase')]);
        }

        $deliverable = new Deliverable;
        $deliverable->forceFill([...$data, 'project_id' => $project->id])->save();

        return $deliverable;
    }

    public function link(Deliverable $deliverable, string $type, string $id, User $viewer): void
    {
        $record = $this->records->resolve($type, [$id], $viewer)[0] ?? null;

        if ($record === null) {
            throw ValidationException::withMessages(['linkTerm' => __('projects::deliverables.errors.record')]);
        }

        $deliverable->forceFill(['linked_type' => $type, 'linked_id' => $record->id, 'linked_title' => mb_substr($record->title, 0, 255)])->save();
    }

    public function unlink(Deliverable $deliverable): void
    {
        $deliverable->forceFill(['linked_type' => null, 'linked_id' => null, 'linked_title' => null])->save();
    }

    public function deliver(Deliverable $deliverable, User $actor, bool $delivered): void
    {
        $deliverable->forceFill(['delivered_at' => $delivered ? now() : null, 'delivered_by' => $delivered ? $actor->id : null])->save();
    }

    public function delete(Deliverable $deliverable): void
    {
        $deliverable->delete();
    }

    public function chooseApplication(Project $project, ?string $applicationId): void
    {
        if ($project->kind !== ProjectKind::Application) {
            throw ValidationException::withMessages(['applicationId' => __('projects::deliverables.errors.not_application')]);
        }

        if ($applicationId !== null && $this->applications->find([$applicationId]) === []) {
            throw ValidationException::withMessages(['applicationId' => __('projects::deliverables.errors.application')]);
        }

        $project->forceFill(['application_id' => $applicationId])->save();
    }
}
