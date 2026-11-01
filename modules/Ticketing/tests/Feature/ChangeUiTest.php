<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Ticketing\Application\Changes\ChangePlan;
use Modules\Ticketing\Application\Changes\CreateChange as CreateChangeAction;
use Modules\Ticketing\Application\Changes\SubmitChange;
use Modules\Ticketing\Application\Notifications\TicketUpdate;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Changes\Approvals;
use Modules\Ticketing\Http\Livewire\Changes\ChangeCalendarView;
use Modules\Ticketing\Http\Livewire\Changes\CreateChange;
use Modules\Ticketing\Http\Livewire\Changes\ReviewChange;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    config(['app.display_timezone' => 'Europe/Bucharest']);
    $this->desk = TicketingScenario::create();
    $this->alice = $this->desk->member(DefaultRole::CabMember);
    $this->cab = CabBoard::query()->create(['name' => 'CAB']);
    $this->cab->members()->attach([$this->alice->id, $this->desk->agent->id]);
});

function submittedChange(TicketingScenario $desk, CabBoard $board): Ticket
{
    $change = app(CreateChangeAction::class)->handle(ChangeType::Normal, 'Core switch upgrade', 'End of support.', new ChangePlan(
        ChangeRisk::High, 'Upgrade the stack.', 'Boot the previous image.', 'Check uplinks.',
        CarbonImmutable::parse('2026-11-20 20:00'), CarbonImmutable::parse('2026-11-20 23:00'),
    ), $desk->agent);
    app(SubmitChange::class)->handle($change, $board, $desk->agent);

    return $change->fresh();
}

it('creates a normal change with its plan, the window read in the display time zone', function (): void {
    $this->actingAs($this->desk->agent);

    Livewire::test(CreateChange::class)
        ->set('type', 'normal')
        ->set('title', 'Firewall firmware upgrade')
        ->set('description', 'Security fixes.')
        ->set('implementationPlan', 'Upgrade to 7.4.')
        ->set('rollbackPlan', 'Reinstall 7.2.')
        ->set('plannedStart', '2026-11-14T22:00')
        ->set('plannedEnd', '2026-11-15T02:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $change = Ticket::query()->where('type', TicketType::Change)->sole();
    expect($change->changeDetails?->planned_start?->setTimezone('UTC')->format('Y-m-d H:i'))->toBe('2026-11-14 20:00');
});

it('validates the window and refuses requesters', function (): void {
    $this->actingAs($this->desk->agent);

    Livewire::test(CreateChange::class)
        ->set('title', 'Firewall firmware upgrade')->set('description', 'x')
        ->set('plannedStart', '2026-11-15T02:00')->set('plannedEnd', '2026-11-14T22:00')
        ->call('save')
        ->assertHasErrors(['plannedEnd']);

    $this->actingAs($this->desk->requester)->get(route('ticketing.changes.create'))->assertForbidden();
});

it('submits a change to the CAB from the desk and notifies the approvers, not the requester', function (): void {
    $this->actingAs($this->desk->agent);
    $change = app(CreateChangeAction::class)->handle(ChangeType::Normal, 'Core switch upgrade', 'x', new ChangePlan(
        ChangeRisk::Medium, 'Upgrade.', 'Roll back.', '', CarbonImmutable::parse('2026-11-20 20:00'), CarbonImmutable::parse('2026-11-20 23:00'),
    ), $this->desk->agent);

    Livewire::test(ShowTicket::class, ['ticket' => $change])
        ->set('boardId', $this->cab->id)
        ->call('submitChange')
        ->assertHasNoErrors();

    expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Pending);
    Notification::assertSentTo($this->alice, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'change_submitted' && str_contains($n->url, '/approvals/'));
    Notification::assertNotSentTo($this->desk->agent, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'change_submitted');
});

it('withdraws approvals when the plan is edited on the desk', function (): void {
    $this->actingAs($this->desk->agent);
    $change = submittedChange($this->desk, $this->cab);

    Livewire::test(ShowTicket::class, ['ticket' => $change])
        ->assertSet('plannedStart', '2026-11-20T22:00')
        ->set('plannedStart', '2026-11-27T22:00')
        ->set('plannedEnd', '2026-11-28T01:00')
        ->call('saveChangePlan')
        ->assertHasNoErrors();

    expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Draft);
});

it('lets a CAB member approve from the inbox and tells the requester', function (): void {
    $change = submittedChange($this->desk, $this->cab);
    $this->actingAs($this->alice);

    $this->get(route('ticketing.changes.approvals'))->assertOk()->assertSee($change->number);

    Livewire::test(ReviewChange::class, ['ticket' => $change])
        ->assertSee('Boot the previous image.')
        ->call('approve')
        ->assertHasNoErrors();

    expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Approved);
    Notification::assertSentTo($this->desk->agent, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'change_approved');

    Livewire::test(Approvals::class)->assertDontSee($change->number);
});

it('requires a comment to reject', function (): void {
    $change = submittedChange($this->desk, $this->cab);
    $this->actingAs($this->alice);

    Livewire::test(ReviewChange::class, ['ticket' => $change])
        ->call('reject')
        ->assertHasErrors(['comment'])
        ->set('comment', 'Not during month-end closing.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($change->fresh()->changeDetails?->approval_status)->toBe(ApprovalStatus::Rejected);
    Notification::assertSentTo($this->desk->agent, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'change_rejected');
});

it('keeps the review page from people outside the board', function (): void {
    $change = submittedChange($this->desk, $this->cab);
    $outsider = $this->desk->member(DefaultRole::CabMember);

    $this->actingAs($outsider)->get(route('ticketing.changes.review', $change))->assertForbidden();
    $this->actingAs($this->desk->requester)->get(route('ticketing.changes.approvals'))->assertForbidden();
});

it('shows scheduled changes on the calendar for the desk only', function (): void {
    $change = submittedChange($this->desk, $this->cab);
    $this->actingAs($this->desk->agent);

    Livewire::withQueryParams(['from' => '2026-11-15'])->test(ChangeCalendarView::class)
        ->assertSee($change->number)
        ->set('from', '2026-12-25')
        ->assertDontSee($change->number);

    $this->get(route('ticketing.changes.calendar'))->assertOk();
    $this->get(route('ticketing.changes.calendar', ['from' => '2026-13-99']))->assertOk();

    $this->actingAs($this->alice)->get(route('ticketing.changes.calendar'))->assertForbidden();
});
