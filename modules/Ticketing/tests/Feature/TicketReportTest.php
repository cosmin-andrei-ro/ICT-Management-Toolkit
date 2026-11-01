<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Problems\CreateProblem;
use Modules\Ticketing\Application\Reports\ReportPeriod;
use Modules\Ticketing\Application\Reports\TicketReport;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\StatusTransition;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Reports\TicketReports;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    config(['app.display_timezone' => 'Europe/Bucharest']);
    CarbonImmutable::setTestNow('2026-11-02 12:00:00');
    $this->desk = TicketingScenario::create();
    $this->period = ReportPeriod::between('2026-10-01', '2026-10-31', 'Europe/Bucharest');
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * A ticket created at $created (UTC), resolved $hours later if given.
 */
function reportTicket(string $created, ?float $hours = null, array $attributes = []): Ticket
{
    $createdAt = CarbonImmutable::parse($created, 'UTC');
    $ticket = Ticket::factory()->create([...$attributes, 'created_at' => $createdAt]);

    if ($hours !== null) {
        $ticket->forceFill(['status' => TicketStatus::Resolved, 'resolution_code' => 'fixed', 'resolved_at' => $createdAt->addMinutes((int) ($hours * 60))])->save();
        transition($ticket, null, TicketStatus::Resolved, $ticket->resolved_at->toImmutable());
    }

    return $ticket;
}

function transition(Ticket $ticket, ?TicketStatus $from, TicketStatus $to, CarbonImmutable $at): void
{
    $row = new StatusTransition;
    $row->forceFill(['organization_id' => $ticket->organization_id, 'ticket_id' => $ticket->id, 'from_status' => $from ?? TicketStatus::InProgress, 'to_status' => $to, 'created_at' => $at])->save();
}

function slaTimer(Ticket $ticket, string $metric, string $outcome, string $completed): void
{
    $timer = new SlaTimer;
    $timer->forceFill(['organization_id' => $ticket->organization_id, 'ticket_id' => $ticket->id, 'metric' => $metric, 'target_minutes' => 60, 'completed_at' => CarbonImmutable::parse($completed, 'UTC'), 'outcome' => $outcome])->save();
}

describe('indicators', function (): void {
    it('counts, times to resolve and reopen rate for the period, in the display time zone', function (): void {
        reportTicket('2026-10-05 08:00', 2);
        reportTicket('2026-10-06 08:00', 4);
        $reopened = reportTicket('2026-10-07 08:00', 12);
        transition($reopened, TicketStatus::Resolved, TicketStatus::InProgress, CarbonImmutable::parse('2026-10-08 09:00'));
        reportTicket('2026-10-31 21:30');            // 1 Nov 00:30 in Bucharest: outside
        reportTicket('2026-09-30 21:30');            // 1 Oct 00:30 in Bucharest: inside
        reportTicket('2026-09-20 08:00', 24 * 30);   // created before, resolved in the period (20 Oct)
        app(CreateProblem::class)->handle('Not a service desk ticket', 'x', Impact::Low, Urgency::Low, $this->desk->agent);
        app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => reportTicket('2026-10-10 08:00', 1));

        $summary = app(TicketReport::class)->summary($this->period);

        expect($summary['created'])->toBe(4)
            ->and($summary['resolved'])->toBe(4)
            ->and($summary['median_hours'])->toBe(8.0)
            ->and($summary['average_hours'])->toBe(round((2 + 4 + 12 + 720) / 4, 1))
            ->and($summary['reopened'])->toBe(1)
            ->and($summary['reopen_rate'])->toBe(25.0);
    });

    it('measures SLA compliance on clocks completed in the period, without cancelled ones', function (): void {
        $ticket = reportTicket('2026-10-05 08:00');
        $other = reportTicket('2026-10-06 08:00');
        $third = reportTicket('2026-10-07 08:00');
        slaTimer($ticket, 'response', 'met', '2026-10-05 09:00');
        slaTimer($other, 'response', 'breached', '2026-10-06 12:00');
        slaTimer($third, 'response', 'cancelled', '2026-10-07 12:00');
        slaTimer($ticket, 'resolution', 'met', '2026-11-01 12:00');

        [$response, $resolution] = app(TicketReport::class)->sla($this->period);

        expect($response)->toMatchArray(['met' => 1, 'breached' => 1, 'compliance' => 50.0])
            ->and($resolution['compliance'])->toBeNull();
    });

    it('shows the open backlog per queue and the volume per category', function (): void {
        $printers = Category::factory()->create(['name' => 'Printers']);
        reportTicket('2026-10-05 08:00', null, ['queue_id' => $this->desk->queue->id, 'category_id' => $printers->id]);
        reportTicket('2026-10-06 08:00', null, ['queue_id' => $this->desk->queue->id, 'category_id' => $printers->id]);
        reportTicket('2026-10-07 08:00', 1, ['queue_id' => $this->desk->queue->id]);

        $report = app(TicketReport::class);

        expect($report->backlog()->all())->toEqual([['queue' => 'First line', 'open' => 2, 'oldest' => CarbonImmutable::parse('2026-10-05 08:00', 'UTC')]])
            ->and($report->volumeByCategory($this->period)->all())->toBe([['category' => 'Printers', 'created' => 2], ['category' => null, 'created' => 1]]);
    });
});

describe('periods', function (): void {
    it('refuses invalid or overlong periods', function (string $from, string $until): void {
        ReportPeriod::between($from, $until, 'Europe/Bucharest');
    })->with([
        'reversed' => ['2026-10-31', '2026-10-01'],
        'not a date' => ['2026-02-30', '2026-03-01'],
        'more than a year' => ['2025-01-01', '2026-10-01'],
    ])->throws(InvalidArgumentException::class);
});

describe('screen and export', function (): void {
    it('is for team leads, not agents', function (): void {
        $this->actingAs($this->desk->lead)->get(route('ticketing.reports'))->assertOk();
        $this->actingAs($this->desk->agent)->get(route('ticketing.reports'))->assertForbidden();
    });

    it('shows the indicators and reports an invalid period', function (): void {
        reportTicket('2026-10-05 08:00', 2);
        reportTicket('2026-10-06 08:00', null, ['queue_id' => $this->desk->queue->id]);
        $this->actingAs($this->desk->lead);

        Livewire::test(TicketReports::class)
            ->assertSet('from', '2026-10-04')
            ->assertSet('until', '2026-11-02')
            ->assertSee('First line')
            ->set('from', '2026-12-01')
            ->assertHasErrors(['from']);
    });

    it('exports the period tickets without requesters, for the lead to download', function (): void {
        Storage::fake('exports');
        $ticket = reportTicket('2026-10-05 08:00', 2, ['title' => '=cmd|calc']);
        $this->actingAs($this->desk->lead);

        Livewire::test(TicketReports::class)
            ->set('from', '2026-10-01')
            ->set('until', '2026-10-31')
            ->call('export', 'csv')
            ->assertSee(__('core::exports.download'));

        $export = Export::query()->sole();
        $csv = Storage::disk('exports')->get((string) $export->path);

        expect($export->filename)->toBe('tickets-2026-10-01-2026-10-31.csv')
            ->and($csv)->toContain($ticket->number)
            ->toContain("'=cmd|calc")
            ->toContain('2026-10-05 11:00')
            ->not->toContain($ticket->requester->name)
            ->not->toContain($ticket->requester->email);

        $this->get(route('core.exports.download', $export))->assertOk();
        $this->actingAs($this->desk->agent)->get(route('core.exports.download', $export))->assertForbidden();
    });

    it('ignores unknown export formats', function (): void {
        $this->actingAs($this->desk->lead);

        Livewire::test(TicketReports::class)->call('export', 'pdf');

        expect(Export::query()->count())->toBe(0);
    });
});
