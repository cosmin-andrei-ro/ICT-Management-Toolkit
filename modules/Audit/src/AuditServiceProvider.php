<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Audit\Application\Exports\ComplianceExport;
use Modules\Audit\Application\Metrics\ComplianceMetrics;
use Modules\Audit\Application\PersonalData\AuditPersonalData;
use Modules\Audit\Application\Risks\ComplianceRiskSignals;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Audit\Http\Livewire\MyControls;
use Modules\Audit\Infrastructure\Console\SendComplianceDigestCommand;
use Modules\Audit\Infrastructure\Console\SendTestRemindersCommand;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Core\Http\Navigation\Navigation;

final class AuditServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'audit';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->register('audit::personal_data.sheet', fn (User $person): iterable => app(AuditPersonalData::class)($person)));

        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(ComplianceMetrics::source()));

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(ComplianceExport::TYPE, ComplianceExport::class, 'audit.compliance.view'));
        $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $signals) => $signals->register('audit', fn (): array => (new ComplianceRiskSignals)()));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'audit.framework' => Framework::class,
            'audit.requirement' => Requirement::class,
            'audit.control' => Control::class,
            'audit.control_test' => ControlTest::class,
            'audit.evidence' => Evidence::class,
            'audit.audit' => Audit::class,
            'audit.finding' => Finding::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendTestRemindersCommand::class)->dailyAt('08:05')->withoutOverlapping()->onOneServer();
            $schedule->command(SendComplianceDigestCommand::class)->weeklyOn(1, '08:15')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendTestRemindersCommand::class, SendComplianceDigestCommand::class]);
        }

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('audit::compliance.mine.menu', 'audit.controls.mine', 'audit.controls.mine', fn (User $user): bool => ! $user->can('audit.compliance.view') && MyControls::hasWork($user), order: 29);
            $navigation->add('audit::compliance.menu', 'audit.index', 'audit.frameworks.*', fn (User $user): bool => $user->can('audit.compliance.view'), Navigation::COMPLIANCE);
            $navigation->add('audit::compliance.controls.menu', 'audit.controls.index', 'audit.controls.*', fn (User $user): bool => $user->can('audit.compliance.view'), Navigation::COMPLIANCE);
            $navigation->add('audit::audits.menu', 'audit.audits.index', 'audit.audits.*', fn (User $user): bool => $user->can('audit.compliance.view'), Navigation::COMPLIANCE);
            $navigation->add('audit::audits.findings.menu', 'audit.findings.index', 'audit.findings.*', fn (User $user): bool => $user->can('audit.compliance.view'), Navigation::COMPLIANCE);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'audit.compliance.view',
            'audit.compliance.manage',
        ]);

        $registry->grantToDefaultRole(DefaultRole::ComplianceManager, ['audit.compliance.view', 'audit.compliance.manage']);
        // Auditors and governance staff read the frameworks and the controls.
        $registry->grantToDefaultRole(DefaultRole::Auditor, ['audit.compliance.view']);
        $registry->grantToDefaultRole(DefaultRole::SecurityOfficer, ['audit.compliance.view']);
        $registry->grantToDefaultRole(DefaultRole::RiskManager, ['audit.compliance.view']);
    }
}
