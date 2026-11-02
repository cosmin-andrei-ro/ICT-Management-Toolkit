<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Events\RiskSignalsChanged;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Links\Risks;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Risk\Application\Exports\RiskRegisterExport;
use Modules\Risk\Application\Links\RegisterRisks;
use Modules\Risk\Application\Links\RiskRecords;
use Modules\Risk\Application\Listeners\RefreshSuggestionsOnSignal;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;
use Modules\Risk\Domain\Models\Suggestion;
use Modules\Risk\Infrastructure\Console\RefreshSuggestionsCommand;
use Modules\Risk\Infrastructure\Console\SendReviewRemindersCommand;
use Modules\Risk\Infrastructure\Console\SendRiskDigestCommand;

final class RiskServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'risk';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(Risks::class, RegisterRisks::class);

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(RiskRegisterExport::TYPE, RiskRegisterExport::class, 'risk.risk.view'));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->callAfterResolving(LinkedRecords::class, function (LinkedRecords $records): void {
            $records->register('risk.risk', RiskRecords::resolve(...));
            $records->registerSearch('risk.risk', 'risk::risks.linked_kind', RiskRecords::search(...));
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(RiskSignalsChanged::class, RefreshSuggestionsOnSignal::class);

        Relation::morphMap([
            'risk.risk' => Risk::class,
            'risk.catalog_entry' => CatalogEntry::class,
            'risk.settings' => RiskSettings::class,
            'risk.suggestion' => Suggestion::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendReviewRemindersCommand::class)->dailyAt('08:00')->withoutOverlapping()->onOneServer();
            $schedule->command(RefreshSuggestionsCommand::class)->dailyAt('06:30')->withoutOverlapping()->onOneServer();
            $schedule->command(SendRiskDigestCommand::class)->weeklyOn(1, '08:10')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendReviewRemindersCommand::class, RefreshSuggestionsCommand::class, SendRiskDigestCommand::class]);
        }

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('risk::risks.mine.menu', 'risk.mine', 'risk.mine', fn (User $user): bool => ! $user->can('risk.risk.view') && Risk::query()->ownedBy($user)->exists(), order: 29);
            $navigation->add('risk::risks.menu', 'risk.index', 'risk.risks.*', fn (User $user): bool => $user->can('risk.risk.view'), Navigation::RESILIENCE);
        });
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'risk.risk.view',
            'risk.risk.manage',
            'risk.settings.manage',
            // Accepting a residual risk from the organization's threshold.
            'risk.acceptance.approve',
        ]);

        $registry->grantToDefaultRole(DefaultRole::RiskManager, ['risk.risk.view', 'risk.risk.manage', 'risk.settings.manage', 'risk.acceptance.approve']);
        // Governance and IT staff read the register.
        $registry->grantToDefaultRole(DefaultRole::SecurityOfficer, ['risk.risk.view']);
        $registry->grantToDefaultRole(DefaultRole::ContinuityCoordinator, ['risk.risk.view']);
        $registry->grantToDefaultRole(DefaultRole::ApplicationManager, ['risk.risk.view']);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['risk.risk.view']);
        // Compliance staff open the risks linked as evidence of controls.
        $registry->grantToDefaultRole(DefaultRole::ComplianceManager, ['risk.risk.view']);
        $registry->grantToDefaultRole(DefaultRole::Auditor, ['risk.risk.view']);
    }
}
