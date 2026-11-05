<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Events\PersonLeftOrganization;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Pam\Application\Listeners\DropAccessOfLeaver;
use Modules\Pam\Application\Metrics\PamMetrics;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\AccountGrant;
use Modules\Pam\Domain\Models\PrivilegedAccount;
use Modules\Pam\Infrastructure\Console\GenerateVaultKeyCommand;
use Modules\Pam\Infrastructure\Console\RekeyVaultCommand;
use Modules\Pam\Infrastructure\Console\RemindVaultDutiesCommand;

final class PamServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'pam';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(PamMetrics::source()));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'pam.account' => PrivilegedAccount::class,
            'pam.account_grant' => AccountGrant::class,
            'pam.access_request' => AccessRequest::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateVaultKeyCommand::class, RekeyVaultCommand::class, RemindVaultDutiesCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RemindVaultDutiesCommand::class)->dailyAt('08:40')->withoutOverlapping()->onOneServer();
        });

        Event::listen(PersonLeftOrganization::class, DropAccessOfLeaver::class);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('pam::accounts.menu', 'pam.accounts.index', 'pam.*', fn (User $user): bool => $user->can('viewAny', PrivilegedAccount::class), order: 38);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            // Ask for credentials granted to you.
            'pam.vault.use',
            // Keep accounts and who may use them. Gives no access to credentials.
            'pam.account.manage',
            // Read who asked for which credential, and why.
            'pam.audit.view',
        ]);

        foreach ([DefaultRole::Agent, DefaultRole::TeamLead, DefaultRole::AssetManager, DefaultRole::ApplicationManager] as $role) {
            $registry->grantToDefaultRole($role, ['pam.vault.use']);
        }

        $registry->grantToDefaultRole(DefaultRole::SecurityOfficer, ['pam.vault.use', 'pam.account.manage', 'pam.audit.view']);
        $registry->grantToDefaultRole(DefaultRole::Auditor, ['pam.audit.view']);
    }
}
