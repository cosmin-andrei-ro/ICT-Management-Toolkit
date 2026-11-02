<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Bia\Application\Exports\BiaExport;
use Modules\Bia\Application\Links\BiaBusinessProcesses;
use Modules\Bia\Application\Risks\BiaRiskSignals;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BiaSettings;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Bia\Infrastructure\Console\SendCoordinatorDigestCommand;
use Modules\Bia\Infrastructure\Console\SendReviewRemindersCommand;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Core\Domain\Time\Duration;
use Modules\Core\Http\Navigation\Navigation;

final class BiaServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'bia';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(BusinessProcesses::class, BiaBusinessProcesses::class);

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $signals) => $signals->register('bia', fn (): array => app(BiaRiskSignals::class)()));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(BiaExport::TYPE, BiaExport::class, 'bia.process.view'));
        // The CMDB item page lists the processes that need the item.
        $this->callAfterResolving(LinkedRecords::class, fn (LinkedRecords $records) => $records->register('bia.assessment', self::linkedProcesses(...)));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'bia.settings' => BiaSettings::class,
            'bia.impact_category' => ImpactCategory::class,
            'bia.time_horizon' => TimeHorizon::class,
            'bia.process' => BusinessProcess::class,
            'bia.assessment' => Assessment::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('bia::processes.mine.menu', 'bia.mine', 'bia.mine', fn (User $user): bool => BusinessProcess::query()->ownedBy($user)->exists(), order: 26);
            $navigation->add('bia::processes.menu', 'bia.processes.index', 'bia.processes.*', fn (User $user): bool => $user->can('bia.process.view'), Navigation::RESILIENCE);
            $navigation->add('bia::gaps.menu', 'bia.gaps', 'bia.gaps', fn (User $user): bool => $user->can('bia.process.view'), Navigation::RESILIENCE);
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendReviewRemindersCommand::class)->dailyAt('07:30')->withoutOverlapping()->onOneServer();
            $schedule->command(SendCoordinatorDigestCommand::class)->weeklyOn(1, '07:45')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendReviewRemindersCommand::class, SendCoordinatorDigestCommand::class]);
        }
    }

    /**
     * Assessments in force or in progress (not superseded ones), as their process.
     *
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    private static function linkedProcesses(array $ids, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Assessment::query()->with('process')->whereKey($ids)->where('status', '!=', AssessmentStatus::Superseded->value)->orderBy('process_id')->limit(100)->get()
            ->filter(fn (Assessment $assessment): bool => $gate->allows('view', $assessment))
            ->map(fn (Assessment $assessment): LinkedRecord => new LinkedRecord(
                $assessment->id,
                __('bia::processes.linked', ['process' => $assessment->process->name]),
                route('bia.processes.show', $assessment->process_id),
                __('bia::processes.linked_status', ['rto' => Duration::format($assessment->rto_minutes), 'status' => $assessment->status->label()]),
            ))
            ->all());
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'bia.process.view',
            'bia.process.manage',
            // Separate from managing: an organization may want approvals by management only.
            'bia.assessment.approve',
            'bia.settings.manage',
        ]);

        // Owner's decision: the coordinator runs the BIA; process owners take part through ownership, not a role.
        $registry->grantToDefaultRole(DefaultRole::ContinuityCoordinator, ['bia.process.view', 'bia.process.manage', 'bia.assessment.approve', 'bia.settings.manage']);
        // IT reads the business's recovery objectives to plan for them.
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['bia.process.view']);
    }
}
