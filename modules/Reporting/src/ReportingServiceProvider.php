<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Http\Home\HomePanels;
use Modules\Reporting\Http\Livewire\Dashboard;
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

        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));
        $this->callAfterResolving(HomePanels::class, fn (HomePanels $panels) => $panels->add(Dashboard::class, 10));
    }

    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Late in the day, so each value is the state the day ended with.
            $schedule->command(TakeSnapshotsCommand::class)->dailyAt('23:30')->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([TakeSnapshotsCommand::class]);
        }
    }
}
