<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Audit\Infrastructure\Console\SendTestRemindersCommand;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
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

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
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
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendTestRemindersCommand::class)->dailyAt('08:05')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendTestRemindersCommand::class]);
        }

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('audit::compliance.mine.menu', 'audit.controls.mine', 'audit.controls.mine', fn (User $user): bool => ! $user->can('audit.compliance.view') && Control::query()->ownedBy($user)->exists(), order: 29);
            $navigation->add('audit::compliance.menu', 'audit.index', 'audit.frameworks.*', fn (User $user): bool => $user->can('audit.compliance.view'), Navigation::COMPLIANCE);
            $navigation->add('audit::compliance.controls.menu', 'audit.controls.index', 'audit.controls.*', fn (User $user): bool => $user->can('audit.compliance.view'), Navigation::COMPLIANCE);
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
