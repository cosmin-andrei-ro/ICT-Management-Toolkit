<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\ChangeTicketStatus;
use Modules\Ticketing\Application\Actions\RequestService;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Notifications\TicketUpdate;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Workflow\InvalidTransition;
use Modules\Ticketing\Http\Filament\Resources\Services\Pages\ManageServices;
use Modules\Ticketing\Http\Livewire\RequestApprovals\RequestApprovalInbox;
use Modules\Ticketing\Http\Livewire\RequestApprovals\ReviewServiceRequest;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
    app(OrganizationContext::class)->set($this->desk->organization);
    $this->approver = $this->desk->member(DefaultRole::Requester);
    $this->service = Service::factory()->create(['name' => 'New laptop', 'form_schema' => [], 'queue_id' => $this->desk->queue->id]);
    $this->service->approvers()->attach($this->approver->id);
});

function requestLaptop(object $test, ?User $requester = null): Ticket
{
    return app(RequestService::class)->handle($test->service, [], 'I need one', $requester ?? $test->desk->requester, TicketChannel::Portal);
}

it('sends a request straight to IT when the service has no approvers', function (): void {
    $this->service->approvers()->detach();

    expect(requestLaptop($this)->status)->toBe(TicketStatus::New);
});

it('holds a request until approved, with its clocks stopped, and tells the approvers', function (): void {
    $ticket = requestLaptop($this);

    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingApproval)
        ->and($ticket->slaTimers()->whereNotNull('running_since')->count())->toBe(0);
    Notification::assertSentTo($this->approver, TicketUpdate::class, fn (TicketUpdate $update): bool => $update->kind === 'approval_needed');

    $this->actingAs($this->approver);
    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])
        ->assertSee('I need one')
        ->call('approve')->assertHasNoErrors()->assertRedirect(route('ticketing.request-approvals'));

    expect($ticket->fresh()->status)->toBe(TicketStatus::New)
        ->and($ticket->slaTimers()->whereNotNull('running_since')->count())->toBeGreaterThan(0)
        ->and($ticket->statusTransitions()->where('to_status', 'new')->value('user_id'))->toBe($this->approver->id);
    Notification::assertSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $update): bool => $update->kind === 'request_approved');
});

it('closes a rejected request, needing a reason the requester can read', function (): void {
    $ticket = requestLaptop($this);
    $this->actingAs($this->approver);

    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])->call('reject')->assertHasErrors('comment');
    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingApproval);

    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])->set('comment', 'Not in the budget')->call('reject')->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Cancelled)
        ->and($ticket->comments()->where('is_internal', false)->value('body'))->toBe('Not in the budget');
    Notification::assertSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $update): bool => $update->kind === 'request_rejected');
});

it('does not let agents skip the approval', function (): void {
    $ticket = requestLaptop($this);

    expect(fn () => app(ChangeTicketStatus::class)->handle($ticket->fresh(), TicketStatus::New, $this->desk->agent))->toThrow(InvalidTransition::class)
        ->and(fn () => app(ChangeTicketStatus::class)->handle($ticket->fresh(), TicketStatus::InProgress, $this->desk->agent))->toThrow(InvalidTransition::class)
        ->and(fn () => app(ResolveTicket::class)->handle($ticket->fresh(), ResolutionCode::Fixed, 'Done', $this->desk->agent))->toThrow(InvalidTransition::class)
        ->and(fn () => app(AssignTicket::class)->handle($ticket->fresh(), $this->desk->agent, null, $this->desk->lead))->toThrow(InvalidArgumentException::class)
        ->and($this->desk->agent->can('decideRequest', $ticket))->toBeFalse()
        ->and($this->desk->agent->can('assign', $ticket))->toBeFalse();
});

it('does not let anyone approve their own request', function (): void {
    $ticket = requestLaptop($this, $this->approver);

    expect($this->approver->can('decideRequest', $ticket))->toBeFalse();
    $this->actingAs($this->approver);
    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])->assertForbidden();
    Livewire::test(RequestApprovalInbox::class)->assertDontSee($ticket->number);
    Notification::assertNotSentTo($this->approver, TicketUpdate::class, fn (TicketUpdate $update): bool => $update->kind === 'approval_needed');
});

it('refuses people who are not approvers', function (): void {
    $ticket = requestLaptop($this);
    $this->actingAs($this->desk->requester);

    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])->assertForbidden();
    Livewire::test(RequestApprovalInbox::class)->assertForbidden();
});

it('lets those who configure Ticketing decide, so nothing gets stuck', function (): void {
    $ticket = requestLaptop($this, $this->approver);
    $this->actingAs($this->desk->lead);

    Livewire::test(RequestApprovalInbox::class)->assertSee($ticket->number);
    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])->call('approve');
    expect($ticket->fresh()->status)->toBe(TicketStatus::New);
});

it('takes one decision only', function (): void {
    $ticket = requestLaptop($this);
    $this->actingAs($this->approver);
    $component = Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id]);
    app(Modules\Ticketing\Application\Actions\DecideServiceRequest::class)->handle($ticket->fresh(), $this->desk->lead, true, '');

    $component->call('approve')->assertForbidden();
    expect(fn () => app(Modules\Ticketing\Application\Actions\DecideServiceRequest::class)->handle($ticket->fresh(), $this->approver, false, 'No'))->toThrow(InvalidArgumentException::class);
    expect($ticket->fresh()->status)->toBe(TicketStatus::New);
});

it('lets the requester withdraw a request that waits', function (): void {
    $ticket = requestLaptop($this);

    expect($this->desk->requester->can('cancel', $ticket))->toBeTrue();
});

it('keeps internal notes from approvers, and other organizations out', function (): void {
    $ticket = requestLaptop($this);
    app(AddComment::class)->handle($ticket, $this->desk->agent, 'Internal: cost centre problem', internal: true);
    $this->actingAs($this->approver);
    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])->assertDontSee('cost centre problem');

    $other = Organization::factory()->create();
    app(OrganizationContext::class)->set($other);
    Livewire::test(ReviewServiceRequest::class, ['ticket' => $ticket->id])->assertNotFound();
});

it('accepts only people of the organization as approvers, and records the change', function (): void {
    $this->actingAs($this->desk->lead);
    $outsider = User::factory()->create();

    Livewire::test(ManageServices::class)
        ->callTableAction('edit', $this->service, data: ['approvers' => [$outsider->id]])
        ->assertHasTableActionErrors();
    expect($this->service->approvers()->pluck('users.id')->all())->toBe([$this->approver->id]);

    Livewire::test(ManageServices::class)
        ->callTableAction('edit', $this->service, data: ['approvers' => [$this->desk->agent->id]])
        ->assertHasNoTableActionErrors();
    expect($this->service->approvers()->pluck('users.id')->all())->toBe([$this->desk->agent->id]);
});
