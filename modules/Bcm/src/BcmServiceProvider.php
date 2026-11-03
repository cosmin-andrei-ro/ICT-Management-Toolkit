<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Bcm\Application\Exports\BcmExport;
use Modules\Bcm\Application\Links\ExerciseRecords;
use Modules\Bcm\Application\Links\PlanRecords;
use Modules\Bcm\Application\Metrics\ContinuityMetrics;
use Modules\Bcm\Application\PersonalData\BcmPersonalData;
use Modules\Bcm\Application\Risks\ContinuityRiskSignals;
use Modules\Bcm\Application\Webhooks\BcmWebhooks;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Infrastructure\Console\SendContinuityDigestCommand;
use Modules\Bcm\Infrastructure\Console\SendPlanRemindersCommand;
use Modules\Core\Application\Api\ApiAbility;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Core\Http\Navigation\Navigation;

final class BcmServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'bcm';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->register('bcm::personal_data.sheet', fn (User $person): iterable => app(BcmPersonalData::class)($person)));
        $this->callAfterResolving(WebhookEvents::class, BcmWebhooks::register(...));

        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(ContinuityMetrics::source()));

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $signals) => $signals->register('bcm', fn (): array => app(ContinuityRiskSignals::class)()));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        // The CMDB item page lists the plans that recover the item.
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(BcmExport::TYPE, BcmExport::class, 'bcm.plan.view'));
        $this->callAfterResolving(LinkedRecords::class, function (LinkedRecords $records): void {
            $records->register('bcm.plan_version', self::linkedPlans(...));
            $records->register('bcm.plan', PlanRecords::resolve(...));
            $records->registerSearch('bcm.plan', 'bcm::plans.linked_kind', PlanRecords::search(...));
            $records->register('bcm.exercise', ExerciseRecords::resolve(...));
            $records->registerSearch('bcm.exercise', 'bcm::exercises.linked_kind', ExerciseRecords::search(...));
        });
    }

    public function boot(): void
    {
        parent::boot();

        BcmWebhooks::listen();

        ApiAbility::register('continuity:read', 'bcm::api.abilities.read');
        ApiAbility::register('continuity:write', 'bcm::api.abilities.write');

        Relation::morphMap([
            'bcm.plan' => Plan::class,
            'bcm.plan_version' => PlanVersion::class,
            'bcm.exercise' => Exercise::class,
            'bcm.activation' => Activation::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('bcm::plans.mine.menu', 'bcm.mine', 'bcm.mine', fn (User $user): bool => ! $user->can('bcm.plan.view') && Plan::query()->ownedBy($user)->exists(), order: 27);
            $navigation->add('bcm::plans.menu', 'bcm.plans.index', 'bcm.plans.*', fn (User $user): bool => $user->can('bcm.plan.view'), Navigation::RESILIENCE);
            $navigation->add('bcm::coverage.menu', 'bcm.coverage', 'bcm.coverage', fn (User $user): bool => $user->can('bcm.plan.view'), Navigation::RESILIENCE);
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendPlanRemindersCommand::class)->dailyAt('07:35')->withoutOverlapping()->onOneServer();
            $schedule->command(SendContinuityDigestCommand::class)->weeklyOn(1, '07:50')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendPlanRemindersCommand::class, SendContinuityDigestCommand::class]);
        }
    }

    /**
     * Versions in force or in progress (not superseded ones), as their plan.
     *
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    private static function linkedPlans(array $ids, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(PlanVersion::query()->with('plan')->whereKey($ids)->where('status', '!=', VersionStatus::Superseded->value)->limit(100)->get()
            ->filter(fn (PlanVersion $version): bool => $gate->allows('view', $version))
            ->map(fn (PlanVersion $version): LinkedRecord => new LinkedRecord(
                $version->id,
                __('bcm::plans.linked', ['type' => $version->plan->type->label(), 'plan' => $version->plan->name]),
                route('bcm.plans.show', $version->plan_id),
                $version->status->label(),
            ))
            ->all());
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'bcm.plan.view',
            'bcm.plan.manage',
            // IT recovery plans only: team leads write them (owner's decision).
            'bcm.drp.manage',
            'bcm.plan.approve',
        ]);

        $registry->grantToDefaultRole(DefaultRole::ContinuityCoordinator, ['bcm.plan.view', 'bcm.plan.manage', 'bcm.drp.manage', 'bcm.plan.approve']);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['bcm.plan.view', 'bcm.drp.manage']);
        // Compliance staff open the plans and exercises linked as evidence of controls.
        $registry->grantToDefaultRole(DefaultRole::ComplianceManager, ['bcm.plan.view']);
        $registry->grantToDefaultRole(DefaultRole::Auditor, ['bcm.plan.view']);
        // Agents follow the plans during incidents.
        $registry->grantToDefaultRole(DefaultRole::Agent, ['bcm.plan.view']);
    }
}
