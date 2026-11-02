<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Application\Notifications\ReportsDue;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Models\Project;

/**
 * Weekly: each project manager hears of their active projects without a
 * status report in the last `projects.reports.every_days` days.
 */
final readonly class RemindReports
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @return int Managers emailed.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $every = config()->integer('projects.reports.every_days', 14);
        $since = $today->subDays($every);
        $sent = 0;

        foreach (Organization::query()->orderBy('id')->get(['id']) as $organization) {
            $sent += $this->context->runAs($organization->id, function () use ($since): int {
                $due = Project::query()->with('manager')
                    ->where('status', ProjectStatus::Active->value)
                    ->whereNotNull('manager_id')
                    ->where('created_at', '<', $since->endOfDay())
                    ->whereDoesntHave('statusReports', fn ($query) => $query->where('reported_on', '>', $since->toDateString()))
                    ->orderBy('code')
                    ->get()
                    ->groupBy('manager_id');
                $count = 0;

                foreach ($due as $projects) {
                    $manager = $projects->first()?->manager;

                    if ($manager === null || ! $manager->canSignIn()) {
                        continue;
                    }

                    $manager->notify(new ReportsDue(array_values($projects->map(fn (Project $project): array => ['name' => $project->code.' '.$project->name, 'url' => route('projects.show', $project)])->all())));
                    $count++;
                }

                return $count;
            });
        }

        return $sent;
    }
}
