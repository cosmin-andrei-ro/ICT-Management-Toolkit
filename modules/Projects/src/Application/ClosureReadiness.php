<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application;

use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\Risks;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Models\Deliverable;
use Modules\Projects\Domain\Models\Project;

/**
 * What must be true before a project closes (ADR 0054): its required
 * deliverables delivered and, for a new application, the checklist of
 * CLAUDE.md §9 - documentation approved, its CMDB item, assessed risks and
 * the business processes it serves in the BIA with their recovery
 * objectives. Each item is read from its module, never kept here.
 */
final readonly class ClosureReadiness
{
    public const array CHECKS = ['application', 'documentation', 'cmdb', 'risks', 'bia'];

    public function __construct(
        private Applications $applications,
        private Risks $risks,
        private BusinessProcesses $processes,
    ) {}

    /**
     * The new-application checklist; empty for other projects.
     *
     * @return array<string, bool> Check key => done, in order.
     */
    public function checklist(Project $project): array
    {
        if ($project->kind !== ProjectKind::Application) {
            return [];
        }

        $application = $project->application_id !== null ? ($this->applications->find([$project->application_id])[$project->application_id] ?? null) : null;

        if ($application === null) {
            return array_fill_keys(self::CHECKS, false);
        }

        $processes = $application->processIds !== [] ? $this->processes->find($application->processIds) : [];

        return [
            'application' => true,
            'documentation' => $application->documented,
            'cmdb' => $application->configurationItemId !== null,
            'risks' => $this->risks->assessedCount('application', $application->id) > 0,
            'bia' => $processes !== [] && array_filter($processes, fn ($process): bool => $process->rtoMinutes === null) === [],
        ];
    }

    /**
     * Why the project cannot close yet, as sentences; empty when it can.
     *
     * @return list<string>
     */
    public function blockers(Project $project): array
    {
        $blockers = [];

        foreach ($this->checklist($project) as $check => $done) {
            if (! $done) {
                $blockers[] = (string) __('projects::deliverables.checks.'.$check);
            }
        }

        $missing = $project->deliverables()->where('required', true)->whereNull('delivered_at')->orderBy('title')->get()
            ->map(fn (Deliverable $deliverable): string => $deliverable->title)->all();

        if ($missing !== []) {
            $blockers[] = (string) __('projects::deliverables.missing', ['titles' => implode(', ', $missing)]);
        }

        return $blockers;
    }
}
