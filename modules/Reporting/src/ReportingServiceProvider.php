<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalData;
use Modules\Core\Http\Home\HomePanels;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Reporting\Application\Actions\ManageReportSubscriptions;
use Modules\Reporting\Application\PersonalData\ReportingPersonalData;
use Modules\Reporting\Domain\Models\ReportSubscription;
use Modules\Reporting\Http\Livewire\Dashboard;
use Modules\Reporting\Infrastructure\Console\SendMonthlyReportsCommand;
use Modules\Reporting\Infrastructure\Console\TakeSnapshotsCommand;

final class ReportingServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'reporting';
    }

    public function register(): void
    {
        parent::register();
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->register('reporting::personal_data.sheet', fn (User $person): iterable => app(ReportingPersonalData::class)($person)));
        $this->callAfterResolving(PersonalData::class, fn (PersonalData $data) => $data->registerEraser(ReportingPersonalData::erase(...)));

        $this->callAfterResolving(PermissionRegistry::class, function (PermissionRegistry $registry): void {
            $registry->register([
                // The management overview and the monthly report: every module's figures.
                ManageReportSubscriptions::VIEW,
                // Who receives the monthly report.
                ManageReportSubscriptions::MANAGE,
            ]);
        });
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->callAfterResolving(HomePanels::class, fn (HomePanels $panels) => $panels->add(Dashboard::class, 10));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'reporting.report_subscription' => ReportSubscription::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Late in the day, so each value is the state the day ended with.
            $schedule->command(TakeSnapshotsCommand::class)->dailyAt('23:30')->withoutOverlapping()->onOneServer();
            // After the last snapshot of the month.
            $schedule->command(SendMonthlyReportsCommand::class)->monthlyOn(1, '07:00')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([TakeSnapshotsCommand::class, SendMonthlyReportsCommand::class]);
        }

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('reporting::management.menu', 'reporting.management', 'reporting.management', fn (User $user): bool => $user->can(ManageReportSubscriptions::VIEW), order: 85);
        });
    }
}
