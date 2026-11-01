<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Api\ApiAbility;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Exports\ExportRegistry;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Ticketing\Application\Email\MailboxReader;
use Modules\Ticketing\Application\Email\ReplyAddress;
use Modules\Ticketing\Application\Notifications\SendChangeNotifications;
use Modules\Ticketing\Application\Notifications\SendSlaNotifications;
use Modules\Ticketing\Application\Notifications\SendTicketNotifications;
use Modules\Ticketing\Application\Reports\TicketExport;
use Modules\Ticketing\Domain\Events\ChangeApproved;
use Modules\Ticketing\Domain\Events\ChangeRejected;
use Modules\Ticketing\Domain\Events\ChangeSubmitted;
use Modules\Ticketing\Domain\Events\SlaBreached;
use Modules\Ticketing\Domain\Events\SlaWarning;
use Modules\Ticketing\Domain\Events\TicketAssigned;
use Modules\Ticketing\Domain\Events\TicketCommentAdded;
use Modules\Ticketing\Domain\Events\TicketCreated;
use Modules\Ticketing\Domain\Events\TicketResolved;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\ChangeApproval;
use Modules\Ticketing\Domain\Models\ChangeDetails;
use Modules\Ticketing\Domain\Models\ChangeTemplate;
use Modules\Ticketing\Domain\Models\Mailbox;
use Modules\Ticketing\Domain\Models\ProblemDetails;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Infrastructure\Console\AutoCloseTicketsCommand;
use Modules\Ticketing\Infrastructure\Console\CheckSlaCommand;
use Modules\Ticketing\Infrastructure\Console\FetchMailCommand;
use Modules\Ticketing\Infrastructure\Console\PruneMailLogCommand;
use Modules\Ticketing\Infrastructure\Email\ImapMailboxReader;

final class TicketingServiceProvider extends ModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'ticketing';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(MailboxReader::class, ImapMailboxReader::class);
        // Scoped: a long-running worker must see mailboxes added later.
        $this->app->scoped(ReplyAddress::class);

        $this->callAfterResolving(PermissionRegistry::class, $this->registerPermissions(...));
        $this->callAfterResolving(ExportRegistry::class, fn (ExportRegistry $exports) => $exports->register(TicketExport::TYPE, TicketExport::class, 'ticketing.report.view'));
        $this->callAfterResolving(LinkedRecords::class, fn (LinkedRecords $records) => $records->register('ticketing.ticket', self::linkedTickets(...)));
        $this->callAfterResolving(Manual::class, fn (Manual $manual) => $manual->addDirectory($this->modulePath('resources/manual')));

        ApiAbility::register('tickets:read', 'ticketing::api.abilities.read');
        ApiAbility::register('tickets:write', 'ticketing::api.abilities.write');
    }

    public function boot(): void
    {
        parent::boot();

        // Core enforces the morph map; modules add their own entries.
        Relation::morphMap([
            'ticketing.ticket' => Ticket::class,
            'ticketing.category' => Category::class,
            'ticketing.queue' => Queue::class,
            'ticketing.service' => Service::class,
            'ticketing.problem_details' => ProblemDetails::class,
            'ticketing.change_details' => ChangeDetails::class,
            'ticketing.change_approval' => ChangeApproval::class,
            'ticketing.change_template' => ChangeTemplate::class,
            'ticketing.cab_board' => CabBoard::class,
            'ticketing.mailbox' => Mailbox::class,
            'ticketing.sla_timer' => SlaTimer::class,
        ]);

        $this->callAfterResolving(Navigation::class, function (Navigation $navigation): void {
            $navigation->add('ticketing::catalog.menu', 'ticketing.catalog.index', 'ticketing.catalog.*', fn (User $user): bool => $user->can('ticketing.ticket.create'));
            $navigation->add('ticketing::ui.menu.portal', 'ticketing.portal.index', 'ticketing.portal.*', fn (User $user): bool => $user->can('ticketing.ticket.create'));
            $navigation->add('ticketing::ui.menu.desk', 'ticketing.desk.index', 'ticketing.desk.index', fn (User $user): bool => $user->can('ticketing.ticket.view_all'));
            $navigation->add('ticketing::changes.ui.menu_calendar', 'ticketing.changes.calendar', 'ticketing.changes.calendar', fn (User $user): bool => $user->can('ticketing.ticket.view_all'));
            $navigation->add('ticketing::changes.ui.menu_approvals', 'ticketing.changes.approvals', 'ticketing.changes.approvals*', fn (User $user): bool => $user->can('ticketing.change.approve'));
            $navigation->add('ticketing::reports.menu', 'ticketing.reports', 'ticketing.reports', fn (User $user): bool => $user->can('ticketing.report.view'));
            $navigation->add('ticketing::problems.ui.known_errors', 'ticketing.desk.known-errors', 'ticketing.desk.known-errors', fn (User $user): bool => $user->can('ticketing.ticket.view_all'));
        });

        Event::listen([TicketCreated::class, TicketAssigned::class, TicketResolved::class, TicketCommentAdded::class], SendTicketNotifications::class);
        Event::listen([SlaWarning::class, SlaBreached::class], SendSlaNotifications::class);
        Event::listen([ChangeSubmitted::class, ChangeApproved::class, ChangeRejected::class], SendChangeNotifications::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(AutoCloseTicketsCommand::class)->hourly()->withoutOverlapping()->onOneServer();
            $schedule->command(CheckSlaCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command(FetchMailCommand::class)->everyMinute()->withoutOverlapping()->onOneServer();
            $schedule->command(PruneMailLogCommand::class)->daily()->onOneServer();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([AutoCloseTicketsCommand::class, CheckSlaCommand::class, FetchMailCommand::class, PruneMailLogCommand::class]);
        }
    }

    /**
     * Tickets linked elsewhere (e.g. to a configuration item), as the viewer may see them.
     *
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    private static function linkedTickets(array $ids, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Ticket::query()->whereKey($ids)->latest()->limit(50)->get()
            ->filter(fn (Ticket $ticket): bool => $gate->allows('view', $ticket))
            ->map(fn (Ticket $ticket): LinkedRecord => new LinkedRecord(
                $ticket->id,
                $ticket->number.' '.$ticket->title,
                route($gate->allows('work', $ticket) ? 'ticketing.desk.show' : 'ticketing.portal.show', $ticket),
                $ticket->status->label(),
            ))
            ->all());
    }

    private function registerPermissions(PermissionRegistry $registry): void
    {
        $registry->register([
            'ticketing.ticket.create',
            'ticketing.ticket.view_all',
            'ticketing.ticket.work',
            'ticketing.ticket.assign',
            'ticketing.settings.manage',
            'ticketing.problem.manage',
            'ticketing.change.create',
            'ticketing.change.approve',
            // Mailbox credentials: administrators only (they hold every permission).
            'ticketing.mailbox.manage',
            'ticketing.report.view',
        ]);

        // Owner's decision: every agent may manage problems; a separate permission keeps it adjustable.
        $agent = ['ticketing.ticket.create', 'ticketing.ticket.view_all', 'ticketing.ticket.work', 'ticketing.ticket.assign', 'ticketing.problem.manage', 'ticketing.change.create'];

        $registry->grantToDefaultRole(DefaultRole::Requester, ['ticketing.ticket.create']);
        $registry->grantToDefaultRole(DefaultRole::CabMember, ['ticketing.ticket.create', 'ticketing.change.approve']);
        $registry->grantToDefaultRole(DefaultRole::Agent, $agent);
        $registry->grantToDefaultRole(DefaultRole::TeamLead, [...$agent, 'ticketing.settings.manage', 'ticketing.change.approve', 'ticketing.report.view']);
    }
}
