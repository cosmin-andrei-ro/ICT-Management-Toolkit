<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Applications\Application\Exports\PortfolioExport;
use Modules\Applications\Application\Links\ApplicationRecords;
use Modules\Applications\Application\Links\PortfolioApplications;
use Modules\Applications\Application\Metrics\PortfolioMetrics;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Application\PersonalData\ApplicationsPersonalData;
use Modules\Applications\Application\Risks\ApplicationRiskSignals;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\OptionValue;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Infrastructure\Console\SendCertificateRemindersCommand;
use Modules\Applications\Infrastructure\Console\SendPortfolioDigestCommand;
use Modules\Applications\Infrastructure\Console\SendReviewRemindersCommand;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Domain\Risks\RiskSignals;
use Modules\Core\Http\Navigation\Navigation;

final class ApplicationsServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'applications';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->register('applications::personal_data.sheet', fn (User $person): iterable => app(ApplicationsPersonalData::class)($person)));

        $this->callAfterResolving(Metrics::class, fn (Metrics $metrics) => $metrics->register(PortfolioMetrics::source()));

        // Scoped: a long-running worker must see values changed later.
        $this->app->scoped(OptionCatalog::class);
        $this->app->bind(Applications::class, PortfolioApplications::class);
        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(RiskSignals::class, fn (RiskSignals $signals) => $signals->register('applications', fn (): array => (new ApplicationRiskSignals)()));
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(PortfolioExport::TYPE, PortfolioExport::class, 'applications.application.view'));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        // The CMDB item page lists the applications built on the item (5.2 Components); compliance evidence links to applications.
        $this->callAfterResolving(LinkedRecords::class, function (LinkedRecords $records): void {
            $records->register('applications.sheet', self::linkedApplications(...));
            $records->register('applications.application', ApplicationRecords::resolve(...));
            $records->registerSearch('applications.application', 'applications::applications.linked_kind', ApplicationRecords::search(...));
        });
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'applications.application' => Application::class,
            'applications.sheet' => Sheet::class,
            'applications.option_value' => OptionValue::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('applications::applications.mine.menu', 'applications.mine', 'applications.mine', fn (User $user): bool => ! $user->can('applications.application.view') && Application::query()->ownedBy($user)->exists(), order: 28);
            $navigation->add('applications::applications.menu', 'applications.index', 'applications.*', fn (User $user): bool => $user->can('applications.application.view'), Navigation::CONFIGURATION);
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(SendCertificateRemindersCommand::class)->dailyAt('07:40')->withoutOverlapping()->onOneServer();
            $schedule->command(SendReviewRemindersCommand::class)->dailyAt('07:45')->withoutOverlapping()->onOneServer();
            $schedule->command(SendPortfolioDigestCommand::class)->weeklyOn(1, '07:55')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SendCertificateRemindersCommand::class, SendReviewRemindersCommand::class, SendPortfolioDigestCommand::class]);
        }
    }

    /**
     * Versions in force or being written (not superseded ones), as their application.
     *
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    private static function linkedApplications(array $ids, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Sheet::query()->with('application')->whereKey($ids)->where('status', '!=', SheetStatus::Superseded->value)->limit(100)->get()
            ->filter(fn (Sheet $sheet): bool => $gate->allows('view', $sheet))
            ->map(fn (Sheet $sheet): LinkedRecord => new LinkedRecord(
                $sheet->id,
                __('applications::applications.linked', ['application' => $sheet->application->name, 'code' => $sheet->application->code]),
                route('applications.show', $sheet->application_id),
                $sheet->status->label(),
            ))
            ->all());
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'applications.application.view',
            'applications.application.manage',
            'applications.sheet.approve_security',
            'applications.settings.manage',
        ]);

        $registry->grantToDefaultRole(DefaultRole::ApplicationManager, ['applications.application.view', 'applications.application.manage', 'applications.settings.manage']);
        $registry->grantToDefaultRole(DefaultRole::SecurityOfficer, ['applications.application.view', 'applications.sheet.approve_security']);
        // IT staff read the sheets: they support the applications.
        $registry->grantToDefaultRole(DefaultRole::TeamLead, ['applications.application.view']);
        // Compliance staff open the applications linked as evidence of controls.
        $registry->grantToDefaultRole(DefaultRole::ComplianceManager, ['applications.application.view']);
        $registry->grantToDefaultRole(DefaultRole::Auditor, ['applications.application.view']);
        $registry->grantToDefaultRole(DefaultRole::Agent, ['applications.application.view']);
        $registry->grantToDefaultRole(DefaultRole::ContinuityCoordinator, ['applications.application.view']);
    }
}
