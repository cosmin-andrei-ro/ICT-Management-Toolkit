<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Application\Api\ApiAbility;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Projects\Application\Exports\PortfolioExport;
use Modules\Projects\Application\Links\ProjectsSearchRecords;
use Modules\Projects\Application\Metrics\ProjectMetrics;
use Modules\Projects\Application\PersonalData\ProjectsPersonalData;
use Modules\Projects\Domain\Models\Cost;
use Modules\Projects\Domain\Models\Deliverable;
use Modules\Projects\Domain\Models\GateRequest;
use Modules\Projects\Domain\Models\Phase;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\StatusReport;
use Modules\Projects\Domain\Models\Task;
use Modules\Projects\Infrastructure\Console\RemindReportsCommand;

final class ProjectsServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'projects';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->register('projects::personal_data.sheet', fn (User $person): iterable => app(ProjectsPersonalData::class)($person)));

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(ProjectMetrics::source()));
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(PortfolioExport::TYPE, PortfolioExport::class, 'projects.project.view'));
        $this->callAfterResolving(LinkedRecords::class, function (LinkedRecords $records): void {
            $records->register('projects.project', ProjectsSearchRecords::resolve(...));
            $records->registerGlobalSearch('projects.project', 'projects::projects.linked_kind', ProjectsSearchRecords::projects(...));
        });
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
    }

    public function boot(): void
    {
        parent::boot();

        ApiAbility::register('projects:read', 'projects::api.abilities.read');

        Relation::morphMap([
            'projects.project' => Project::class,
            'projects.phase' => Phase::class,
            'projects.task' => Task::class,
            'projects.gate_request' => GateRequest::class,
            'projects.status_report' => StatusReport::class,
            'projects.deliverable' => Deliverable::class,
            'projects.cost' => Cost::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RemindReportsCommand::class)->weeklyOn(1, '08:30')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RemindReportsCommand::class]);
        }

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            // Team members see the projects they work on even without the permission.
            $navigation->add('projects::projects.menu', 'projects.index', 'projects.*', fn (User $user): bool => $user->can('projects.project.view')
                || $user->can('projects.project.manage') || Project::query()->involving($user)->exists(), order: 36);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            // Every project, read only.
            'projects.project.view',
            // Start projects and run any of them.
            'projects.project.manage',
        ]);

        foreach ([DefaultRole::TeamLead, DefaultRole::AssetManager, DefaultRole::ApplicationManager, DefaultRole::ContinuityCoordinator, DefaultRole::SecurityOfficer, DefaultRole::RiskManager, DefaultRole::ComplianceManager, DefaultRole::Auditor] as $role) {
            $registry->grantToDefaultRole($role, ['projects.project.view']);
        }

        $registry->grantToDefaultRole(DefaultRole::ProjectManager, ['projects.project.view', 'projects.project.manage']);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['projects.project.manage']);
    }
}
