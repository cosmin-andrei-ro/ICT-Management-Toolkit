<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Infrastructure\Console\AutoCloseTicketsCommand;

final class TicketingServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'ticketing';
    }

    public function register(): void
    {
        parent::register();

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
    }

    public function boot(): void
    {
        parent::boot();

        // Core enforces the morph map; modules add their own entries.
        Relation::morphMap([
            'ticketing.ticket' => Ticket::class,
            'ticketing.category' => Category::class,
            'ticketing.queue' => Queue::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(AutoCloseTicketsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([AutoCloseTicketsCommand::class]);
        }
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'ticketing.ticket.create',
            'ticketing.ticket.view_all',
            'ticketing.ticket.work',
            'ticketing.ticket.assign',
            'ticketing.settings.manage',
        ]);

        $agent = ['ticketing.ticket.create', 'ticketing.ticket.view_all', 'ticketing.ticket.work', 'ticketing.ticket.assign'];

        $registry->grantToDefaultRole(DefaultRole::Requester, ['ticketing.ticket.create']);
        $registry->grantToDefaultRole(DefaultRole::CabMember, ['ticketing.ticket.create']);
        $registry->grantToDefaultRole(DefaultRole::Agent, $agent);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, [...$agent, 'ticketing.settings.manage']);
    }
}
