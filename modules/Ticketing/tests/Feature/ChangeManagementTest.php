<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AutoCloseResolvedTickets;
use Modules\Ticketing\Application\Actions\ChangeTicketStatus;
use Modules\Ticketing\Application\Actions\CloseTicket;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Changes\ChangeCalendar;
use Modules\Ticketing\Application\Changes\ChangePlan;
use Modules\Ticketing\Application\Changes\CreateChange;
use Modules\Ticketing\Application\Changes\DecideOnChange;
use Modules\Ticketing\Application\Changes\RecordChangeReview;
use Modules\Ticketing\Application\Changes\SubmitChange;
use Modules\Ticketing\Application\Changes\UpdateChangePlan;
use Modules\Ticketing\Domain\Changes\ApprovalDecision;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\ChangeTemplate;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();

    $this->alice = $this->desk->member(DefaultRole::CabMember);
    $this->bob = $this->desk->member(DefaultRole::CabMember);
    $this->cab = CabBoard::query()->create(['name' => 'CAB']);
    $this->cab->members()->attach([$this->alice->id, $this->bob->id, $this->desk->agent->id]);

    $this->ecab = CabBoard::query()->create(['name' => 'ECAB', 'is_emergency' => true]);
    $this->ecab->members()->attach([$this->alice->id]);
});

function plan(string $start = '2026-11-14 22:00', string $end = '2026-11-15 02:00'): ChangePlan
{
    return new ChangePlan(ChangeRisk::Medium, 'Upgrade the firewall firmware to 7.4.', 'Reinstall 7.2 from backup.', 'Ping core switches.', CarbonImmutable::parse($start), CarbonImmutable::parse($end));
}

function draftChange(TicketingScenario $desk, ChangeType $type = ChangeType::Normal, ?ChangePlan $plan = null): Ticket
{
    return app(CreateChange::class)->handle($type, 'Firewall firmware upgrade', 'Security fixes.', $plan ?? plan(), $desk->agent);
}

function approveBy(Ticket $change, User ...$approvers): void
{
    foreach ($approvers as $approver) {
        app(DecideOnChange::class)->handle($change->fresh(), $approver, ApprovalDecision::Approved, '');
    }
}

describe('creating', function (): void {
    it('numbers changes separately, starts normal ones as drafts, without SLA clocks', function (): void {
        $change = draftChange($this->desk);

        expect($change->number)->toBe('CHG-000001')
            ->and($change->changeDetails?->approval_status)->toBe(ApprovalStatus::Draft)
            ->and(SlaTimer::query()->where('ticket_id', $change->id)->count())->toBe(0);
    });

    it('creates pre-approved standard changes from a template', function (): void {
        $template = ChangeTemplate::query()->create([
            'name' => 'Restart print spooler', 'description' => 'Routine', 'risk' => ChangeRisk::Low,
            'implementation_plan' => 'Restart the service.', 'rollback_plan' => 'None needed.',
        ]);

        $change = app(CreateChange::class)->fromTemplate($template, '', CarbonImmutable::parse('2026-11-10 18:00'), CarbonImmutable::parse('2026-11-10 18:15'), $this->desk->agent);

        expect($change->changeDetails?->change_type)->toBe(ChangeType::Standard)
            ->and($change->changeDetails?->isApproved())->toBeTrue()
            ->and($change->changeDetails?->implementation_plan)->toBe('Restart the service.');
    });

    it('refuses standard changes without a template, also in the database', function (): void {
        expect(fn () => app(CreateChange::class)->handle(ChangeType::Standard, 'x', 'x', plan(), $this->desk->agent))
            ->toThrow(InvalidArgumentException::class);

        $change = draftChange($this->desk);
        DB::table('ticketing_change_details')->where('ticket_id', $change->id)->update(['change_type' => 'standard']);
    })->throws(QueryException::class, 'ticketing_change_details_standard_has_template');
});

describe('CAB approval (unanimity)', function (): void {
    it('needs every member except the requester, then authorizes the change', function (): void {
        $change = draftChange($this->desk);
        app(SubmitChange::class)->handle($change, $this->cab, $this->desk->agent);

        approveBy($change, $this->alice);
        expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Pending);

        approveBy($change, $this->bob);
        expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Approved);
    });

    it('never lets the requester approve their own change', function (): void {
        $change = draftChange($this->desk);
        app(SubmitChange::class)->handle($change, $this->cab, $this->desk->agent);

        app(DecideOnChange::class)->handle($change->fresh(), $this->desk->agent, ApprovalDecision::Approved, '');
    })->throws(DomainException::class, 'never its requester');

    it('never lets whoever sent the change or last wrote its plan approve it', function (): void {
        // Requested by the agent, then re-planned by Bob and sent by Alice: only the lead remains to approve.
        $this->cab->members()->attach($this->desk->lead->id);
        app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->lead->givePermissionTo('ticketing.change.approve'));
        $change = draftChange($this->desk);
        app(UpdateChangePlan::class)->handle($change->fresh(), plan('2026-11-21 22:00', '2026-11-22 02:00'), $this->bob);
        app(SubmitChange::class)->handle($change->fresh(), $this->cab, $this->alice);

        expect(fn () => approveBy($change, $this->alice))->toThrow(DomainException::class)
            ->and(fn () => approveBy($change, $this->bob))->toThrow(DomainException::class);

        approveBy($change, $this->desk->lead);
        expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Approved);
    });

    it('cannot be sent to a board on which only the sender could approve', function (): void {
        $solo = CabBoard::query()->create(['name' => 'Just me']);
        $solo->members()->attach($this->alice->id);

        app(SubmitChange::class)->handle(draftChange($this->desk), $solo, $this->alice);
    })->throws(DomainException::class, 'nobody who could approve');

    it('refuses people outside the board and double decisions', function (): void {
        $change = draftChange($this->desk);
        app(SubmitChange::class)->handle($change, $this->cab, $this->desk->agent);
        approveBy($change, $this->alice);

        expect(fn () => approveBy($change, $this->desk->lead))->toThrow(DomainException::class)
            ->and(fn () => approveBy($change, $this->alice))->toThrow(DomainException::class, 'already decided');
    });

    it('is rejected by a single rejection, which needs a reason', function (): void {
        $change = draftChange($this->desk);
        app(SubmitChange::class)->handle($change, $this->cab, $this->desk->agent);

        expect(fn () => app(DecideOnChange::class)->handle($change->fresh(), $this->alice, ApprovalDecision::Rejected, ' '))
            ->toThrow(DomainException::class, 'reason');

        approveBy($change, $this->bob);
        app(DecideOnChange::class)->handle($change->fresh(), $this->alice, ApprovalDecision::Rejected, 'Not during month-end closing.');

        expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Rejected);
    });

    it('starts a new round on resubmission, and withdraws approvals when the plan changes', function (): void {
        $change = draftChange($this->desk);
        app(SubmitChange::class)->handle($change, $this->cab, $this->desk->agent);
        approveBy($change, $this->alice, $this->bob);
        expect($change->fresh()->changeDetails?->isApproved())->toBeTrue();

        $withdrawn = app(UpdateChangePlan::class)->handle($change->fresh(), plan('2026-11-21 22:00', '2026-11-22 02:00'), $this->desk->agent);

        expect($withdrawn)->toBeTrue()
            ->and($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Draft);

        app(SubmitChange::class)->handle($change->fresh(), $this->cab, $this->desk->agent);
        approveBy($change, $this->alice, $this->bob);

        expect($change->fresh()->changeDetails?->approval_round)->toBe(2)
            ->and($change->fresh()->changeDetails?->isApproved())->toBeTrue();
    });

    it('requires plans and a window before submission', function (): void {
        $change = draftChange($this->desk, plan: new ChangePlan(ChangeRisk::Low, 'Do it', '', '', null, null));

        app(SubmitChange::class)->handle($change, $this->cab, $this->desk->agent);
    })->throws(DomainException::class, 'rollback plan');
});

describe('emergency changes (ECAB)', function (): void {
    it('go to an emergency CAB, never to a regular one, and the other way round', function (): void {
        $emergency = draftChange($this->desk, ChangeType::Emergency);
        $normal = draftChange($this->desk);

        expect(fn () => app(SubmitChange::class)->handle($emergency, $this->cab, $this->desk->agent))->toThrow(DomainException::class, 'ECAB')
            ->and(fn () => app(SubmitChange::class)->handle($normal, $this->ecab, $this->desk->agent))->toThrow(DomainException::class, 'regular CAB');

        app(SubmitChange::class)->handle($emergency, $this->ecab, $this->desk->agent);
        approveBy($emergency, $this->alice);

        expect($emergency->fresh()->changeDetails?->isApproved())->toBeTrue();
    });

    it('cannot close without a post-implementation review, not even automatically', function (): void {
        $change = draftChange($this->desk, ChangeType::Emergency);
        app(SubmitChange::class)->handle($change, $this->ecab, $this->desk->agent);
        approveBy($change, $this->alice);
        app(ChangeTicketStatus::class)->handle($change->fresh(), TicketStatus::InProgress, $this->desk->agent);
        app(ResolveTicket::class)->handle($change->fresh(), ResolutionCode::Implemented, 'Patched.', $this->desk->agent);

        expect(fn () => app(CloseTicket::class)->handle($change->fresh(), $this->desk->agent))->toThrow(DomainException::class, 'review');

        $change->forceFill(['resolved_at' => now()->subDays(10)])->save();
        expect(app(AutoCloseResolvedTickets::class)->handle())->toBe(0);

        app(RecordChangeReview::class)->handle($change->fresh(), 'Critical CVE; patched in 20 minutes, no impact.');
        app(CloseTicket::class)->handle($change->fresh(), $this->desk->agent);

        expect($change->fresh()->status)->toBe(TicketStatus::Closed);
    });
});

describe('implementation', function (): void {
    it('cannot start or be resolved before authorization', function (): void {
        $change = draftChange($this->desk);

        expect(fn () => app(ChangeTicketStatus::class)->handle($change->fresh(), TicketStatus::InProgress, $this->desk->agent))
            ->toThrow(DomainException::class, 'not authorized')
            ->and(fn () => app(ResolveTicket::class)->handle($change->fresh(), ResolutionCode::Implemented, 'x', $this->desk->agent))
            ->toThrow(DomainException::class, 'not authorized');
    });

    it('flags overlapping windows of scheduled changes', function (): void {
        $first = draftChange($this->desk);
        $overlapping = draftChange($this->desk, plan: plan('2026-11-15 01:00', '2026-11-15 03:00'));
        $later = draftChange($this->desk, plan: plan('2026-11-15 03:00', '2026-11-15 04:00'));

        foreach ([$first, $overlapping, $later] as $change) {
            app(SubmitChange::class)->handle($change->fresh(), $this->cab, $this->desk->agent);
        }

        expect(app(ChangeCalendar::class)->conflictsWith($first->fresh())->pluck('id')->all())->toBe([$overlapping->id])
            ->and(app(ChangeCalendar::class)->conflictsWith($later->fresh())->pluck('id')->all())->toBe([]);
    });
});

it('stores a window given in local time as the right instant', function (): void {
    $change = draftChange($this->desk, plan: new ChangePlan(
        ChangeRisk::Low, 'x', 'y', '',
        CarbonImmutable::parse('2026-11-14 22:00', 'Europe/Bucharest'),
        CarbonImmutable::parse('2026-11-15 02:00', 'Europe/Bucharest'),
    ));

    expect($change->fresh()->changeDetails?->planned_start?->setTimezone('Europe/Bucharest')->format('Y-m-d H:i'))->toBe('2026-11-14 22:00');
});
