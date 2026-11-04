<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\ChangeTicketStatus;
use Modules\Ticketing\Application\Actions\CloseTicket;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Changes\ChangePlan;
use Modules\Ticketing\Application\Changes\CreateChange;
use Modules\Ticketing\Application\Changes\DecideOnChange;
use Modules\Ticketing\Application\Changes\RecordChangeReview;
use Modules\Ticketing\Application\Changes\SubmitChange;
use Modules\Ticketing\Application\Problems\CreateProblem;
use Modules\Ticketing\Application\Problems\MarkKnownError;
use Modules\Ticketing\Application\Problems\UpdateProblemAnalysis;
use Modules\Ticketing\Application\Sla\CheckSlaDeadlines;
use Modules\Ticketing\Domain\Changes\ApprovalDecision;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\TeamOla;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * An IT support team that has been running for three months: queues, categories,
 * the catalog, about sixty tickets opened and worked over the last 90 days
 * (some late on their SLA), problems with a known error, and changes
 * approved by the CAB. Everything goes through the desk's own actions.
 */
final class TicketingDemoSeeder extends Seeder
{
    /** @var list<array{0: TicketType, 1: string, 2: string, 3: string, 4: Impact, 5: Urgency}> type, title, description, category, impact, urgency */
    private const array TICKETS = [
        [TicketType::Incident, 'Laptop does not start', 'The power light blinks but nothing appears on screen.', 'laptops', Impact::Low, Urgency::High],
        [TicketType::Incident, 'Printer on floor 2 is offline', 'It shows "offline" since this morning.', 'printers', Impact::Medium, Urgency::Medium],
        [TicketType::Incident, 'Cannot connect to the VPN', 'The client says the certificate is not trusted.', 'access', Impact::Low, Urgency::Medium],
        [TicketType::Incident, 'ERP is very slow', 'Posting an invoice takes over a minute for the whole finance team.', 'applications', Impact::High, Urgency::High],
        [TicketType::Incident, 'E-mail not syncing on phone', 'New messages stop arriving on the phone after lunch.', 'access', Impact::Low, Urgency::Low],
        [TicketType::Incident, 'Warehouse scanners lose Wi-Fi', 'Handheld scanners in the Cluj warehouse drop the connection every few minutes.', 'network', Impact::High, Urgency::Medium],
        [TicketType::Incident, 'Shared drive full', 'Saving to the Finance share fails: not enough space.', 'applications', Impact::Medium, Urgency::High],
        [TicketType::Incident, 'Monitor flickers', 'The second screen flickers when the laptop is docked.', 'laptops', Impact::Low, Urgency::Low],
        [TicketType::ServiceRequest, 'New laptop for a new colleague', 'Starts on Monday in Logistics.', 'laptops', Impact::Low, Urgency::Medium],
        [TicketType::ServiceRequest, 'Access to the Sales reports', 'Needs read access to the monthly sales dashboard.', 'access', Impact::Low, Urgency::Low],
        [TicketType::ServiceRequest, 'Install the label printer driver', 'For the new label printer at the packing station.', 'printers', Impact::Low, Urgency::Low],
        [TicketType::ServiceRequest, 'VPN access for remote work', 'Working from home two days a week.', 'access', Impact::Low, Urgency::Low],
    ];

    public function run(DemoCompany $company, OrganizationContext $context): void
    {
        mt_srand(2026);

        $context->runAs($company->organization, function () use ($company): void {
            $queue = Queue::query()->create(['team_id' => $company->teams['desk']->id, 'name' => 'First line', 'is_default' => true]);
            Queue::query()->create(['team_id' => $company->teams['infrastructure']->id, 'name' => 'Infrastructure']);
            TeamOla::query()->create(['team_id' => $company->teams['desk']->id, 'minutes' => 240]);
            TeamOla::query()->create(['team_id' => $company->teams['infrastructure']->id, 'minutes' => 960]);

            $hardware = Category::query()->create(['name' => 'Hardware', 'sort_order' => 1]);
            $categories = [
                'laptops' => Category::query()->create(['name' => 'Laptops', 'parent_id' => $hardware->id]),
                'printers' => Category::query()->create(['name' => 'Printers', 'parent_id' => $hardware->id]),
                'access' => Category::query()->create(['name' => 'Access', 'sort_order' => 2]),
                'network' => Category::query()->create(['name' => 'Network', 'sort_order' => 3]),
                'applications' => Category::query()->create(['name' => 'Business applications', 'sort_order' => 4]),
            ];

            Service::query()->create([
                'name' => 'New laptop',
                'description' => 'A laptop for a new colleague or a replacement for a broken one.',
                'category_id' => $categories['laptops']->id,
                'queue_id' => $queue->id,
                'impact' => Impact::Low,
                'urgency' => Urgency::Medium,
                'form_schema' => [
                    ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true, 'options' => ['Standard 14"', 'Developer 16"']],
                    ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'required' => true],
                    ['key' => 'replacement', 'label' => 'This replaces a broken laptop', 'type' => 'checkbox'],
                ],
                'sort_order' => 1,
            ]);

            Service::query()->create([
                'name' => 'VPN access',
                'description' => 'Remote access to the company network.',
                'category_id' => $categories['access']->id,
                'impact' => Impact::Low,
                'urgency' => Urgency::Low,
                'form_schema' => [
                    ['key' => 'reason', 'label' => 'Why do you need it?', 'type' => 'textarea', 'required' => true],
                    ['key' => 'policy', 'label' => 'I have read the remote access policy', 'type' => 'checkbox', 'required' => true],
                ],
                'sort_order' => 2,
            ]);

            $incidents = $this->tickets($company, $queue, $categories);
            $this->problems($company, $incidents);
            $this->changes($company);

            DemoClock::reset();
            // Mark the SLA breaches the history leaves behind.
            app(CheckSlaDeadlines::class)->handle();
        });

        DemoClock::reset();
    }

    /**
     * About sixty tickets over the last 90 days: older ones resolved and
     * closed, recent ones still in the queue at every stage.
     *
     * @param  array<string, Category>  $categories
     * @return list<Ticket> The incidents about the warehouse Wi-Fi, for a problem.
     */
    private function tickets(DemoCompany $company, Queue $queue, array $categories): array
    {
        $employees = $company->employees();
        $agents = [$company->user('agent'), $company->user('agent2')];
        $wifi = [];

        for ($i = 0; $i < 60; $i++) {
            [$type, $title, $description, $category, $impact, $urgency] = self::TICKETS[$i % count(self::TICKETS)];
            $daysAgo = (int) round(88 * (59 - $i) / 59);
            $opened = DemoClock::daysAgo($daysAgo, sprintf('%02d:%02d', mt_rand(8, 16), mt_rand(0, 59)));
            // Today's tickets came in before now, never in the future.
            $opened = $opened->min(DemoClock::today()->setTimeFrom(now())->subMinutes(30 + 20 * (59 - $i)));
            $requester = $employees[$i % count($employees)];
            $agent = $agents[$i % 2];

            DemoClock::at($opened);
            $ticket = app(CreateTicket::class)->handle(
                new NewTicketData($type, $title, $description, $impact, $urgency, [TicketChannel::Portal, TicketChannel::Email, TicketChannel::Phone][$i % 3], $categories[$category]->id, $queue->id),
                $requester,
                $requester,
            );

            if ($title === 'Warehouse scanners lose Wi-Fi') {
                $wifi[] = $ticket;
            }

            // The newest tickets wait in the queue; one in eight stays late on purpose.
            if ($daysAgo <= 1 && $i % 2 === 0) {
                continue;
            }

            $this->step($opened, mt_rand(15, 90), fn () => app(AssignTicket::class)->handle($ticket->refresh(), $agent, null, $company->user('teamlead')));
            $this->step($opened, mt_rand(100, 240), fn () => app(ChangeTicketStatus::class)->handle($ticket->refresh(), TicketStatus::InProgress, $agent));
            $this->step($opened, mt_rand(250, 400), fn () => app(AddTicketComment::class)->handle($ticket->refresh(), $agent, 'Looking into it; I will update you shortly.', false));

            $late = $i % 8 === 3;

            if ($daysAgo <= 4 || $late) {
                if ($i % 5 === 0) {
                    $this->step($opened, 420, fn () => app(ChangeTicketStatus::class)->handle($ticket->refresh(), TicketStatus::Pending, $agent));
                }

                continue;
            }

            $resolvedAfter = $impact === Impact::High ? mt_rand(180, 600) : mt_rand(600, 2400);
            $this->step($opened, $resolvedAfter, fn () => app(ResolveTicket::class)->handle(
                $ticket->refresh(),
                $type === TicketType::ServiceRequest ? ResolutionCode::Fulfilled : ResolutionCode::Fixed,
                $type === TicketType::ServiceRequest ? 'Done, as requested.' : 'Fixed; please tell us if it happens again.',
                $agent,
            ));

            if ($daysAgo > 7) {
                $this->step($opened, $resolvedAfter + 3 * 24 * 60, fn () => app(CloseTicket::class)->handle($ticket->refresh(), null));
            }
        }

        return $wifi;
    }

    /**
     * @param  list<Ticket>  $wifi
     */
    private function problems(DemoCompany $company, array $wifi): void
    {
        $manager = $company->user('teamlead');

        DemoClock::at(DemoClock::daysAgo(30, '11:00'));
        $problem = app(CreateProblem::class)->handle('Warehouse Wi-Fi drops scanners', 'Scanners in Cluj lose the wireless network several times an hour.', Impact::High, Urgency::Medium, $manager, array_values(array_filter($wifi, fn (Ticket $ticket): bool => $ticket->fresh()?->status !== TicketStatus::Closed)));
        app(UpdateProblemAnalysis::class)->handle($problem, 'Access points roam scanners too aggressively between channels 1 and 6.', 'Pin the scanners to the 5 GHz SSID "WH-SCAN" until the access points are reconfigured.');
        app(MarkKnownError::class)->handle($problem->refresh());

        DemoClock::at(DemoClock::daysAgo(12, '10:00'));
        $printer = app(CreateProblem::class)->handle('Floor 2 printer drops off the network', 'The printer loses its IP address after a power cut.', Impact::Medium, Urgency::Low, $manager);
        app(UpdateProblemAnalysis::class)->handle($printer, 'The printer takes a DHCP address instead of its reserved one.', 'Power-cycle the printer, then print a configuration page to check its address.');
    }

    private function changes(DemoCompany $company): void
    {
        $cab = CabBoard::query()->create(['name' => 'CAB']);
        $cab->members()->attach([$company->user('cab')->id, $company->user('teamlead')->id]);
        $ecab = CabBoard::query()->create(['name' => 'ECAB', 'is_emergency' => true]);
        $ecab->members()->attach([$company->user('teamlead')->id]);

        $requester = $company->user('agent2');
        // Unanimity: every member who may approve, except the requester.
        $approvers = [$company->user('cab'), $company->user('teamlead')];

        foreach ([
            [60, 'Firewall firmware upgrade', 'Security fixes for the perimeter firewall.', ChangeRisk::Medium, 'implemented'],
            [35, 'Reconfigure warehouse access points', 'Fix scanner roaming in Cluj (known error).', ChangeRisk::Low, 'implemented'],
            [10, 'ERP database server memory upgrade', 'Add 64 GB to the ERP database server.', ChangeRisk::Medium, 'approved'],
            [2, 'Replace core switch in the server room', 'The core switch reaches end of support.', ChangeRisk::High, 'submitted'],
        ] as [$daysAgo, $title, $description, $risk, $stage]) {
            // The window, five days after the request (still ahead for recent ones); computed before the clock is set.
            $start = DemoClock::daysAgo($daysAgo - 5, '22:00');
            $requested = DemoClock::daysAgo($daysAgo, '10:00');
            DemoClock::at($requested);
            $change = app(CreateChange::class)->handle(ChangeType::Normal, $title, $description, new ChangePlan(
                $risk, 'Follow the vendor procedure step by step; keep the console open.', 'Restore the previous configuration from the backup taken before.', 'Check service availability from the monitoring dashboard.',
                $start, $start->addHours(3),
            ), $requester);
            app(SubmitChange::class)->handle($change->refresh(), $cab, $requester);

            if ($stage === 'submitted') {
                continue;
            }

            foreach ($approvers as $offset => $approver) {
                $this->step($requested, 60 * ($offset + 1), fn () => app(DecideOnChange::class)->handle($change->refresh(), $approver, ApprovalDecision::Approved, 'Agreed.'));
            }

            if ($stage === 'implemented') {
                DemoClock::at($start->addHours(1));
                app(ChangeTicketStatus::class)->handle($change->refresh(), TicketStatus::InProgress, $requester);
                DemoClock::at($start->addHours(2));
                app(ResolveTicket::class)->handle($change->refresh(), ResolutionCode::Implemented, 'Implemented within the window; no impact observed.', $requester);
                DemoClock::at($start->addDays(1));
                app(RecordChangeReview::class)->handle($change->refresh(), 'Went as planned. No follow-up needed.');
            }
        }
    }

    /**
     * Runs a step some minutes after a moment, unless that would be in the future.
     *
     * @param  callable(): mixed  $step
     */
    private function step(CarbonImmutable $from, int $minutes, callable $step): void
    {
        $at = $from->addMinutes($minutes);

        if ($at->isAfter(DemoClock::today()->setTimeFrom(now()))) {
            return;
        }

        DemoClock::at($at);
        $step();
    }
}
