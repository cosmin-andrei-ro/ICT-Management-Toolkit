<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Notifications\TicketUpdate;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket as DeskShowTicket;
use Modules\Ticketing\Http\Livewire\Desk\TicketQueue;
use Modules\Ticketing\Http\Livewire\Portal\CreateTicket;
use Modules\Ticketing\Http\Livewire\Portal\ShowTicket as PortalShowTicket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Storage::fake('attachments');
    Notification::fake();
    $this->desk = TicketingScenario::create();
});

describe('portal', function (): void {
    it('opens a ticket with an attachment', function (): void {
        $this->actingAs($this->desk->requester);

        Livewire::test(CreateTicket::class)
            ->set('title', 'Laptop does not boot')
            ->set('description', 'Black screen after the logo.')
            ->set('urgency', 'high')
            ->set('files', [UploadedFile::fake()->createWithContent('error.txt', 'BOOTMGR is missing')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $ticket = Ticket::query()->sole();
        expect($ticket->title)->toBe('Laptop does not boot')
            ->and($ticket->requester_id)->toBe($this->desk->requester->id)
            ->and($ticket->attachments()->sole()->is_internal)->toBeFalse();

        Notification::assertSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'created');
    });

    it('rejects a category of another organization', function (): void {
        $foreign = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Modules\Ticketing\Domain\Models\Category::factory()->create());
        $this->actingAs($this->desk->requester);

        Livewire::test(CreateTicket::class)
            ->set('title', 'Something broke')
            ->set('description', 'Details')
            ->set('categoryId', $foreign->id)
            ->call('save')
            ->assertHasErrors(['categoryId']);
    });

    it('shows only public conversation to the requester', function (): void {
        $ticket = $this->desk->ticket();
        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'Public: we are on it', internal: false);
        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'Internal: user has admin rights, careful', internal: true);

        $this->actingAs($this->desk->requester)
            ->get(route('ticketing.portal.show', $ticket))
            ->assertOk()
            ->assertSee('Public: we are on it')
            ->assertDontSee('Internal: user has admin rights');
    });

    it('shows internal notes on the desk only to people who may read them', function (): void {
        $ticket = $this->desk->ticket();
        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'Public: we are on it', internal: false);
        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'Internal: user has admin rights, careful', internal: true);
        // A custom role that may work tickets but not read everything about them.
        $technician = $this->desk->member(DefaultRole::Requester);
        app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $technician->givePermissionTo('ticketing.ticket.work'));

        $this->actingAs($technician);
        Livewire::test(DeskShowTicket::class, ['ticket' => $ticket])->assertSee('Public: we are on it')->assertDontSee('Internal: user has admin rights');

        $this->actingAs($this->desk->agent);
        Livewire::test(DeskShowTicket::class, ['ticket' => $ticket])->assertSee('Internal: user has admin rights');
    });

    it('hides tickets of other requesters and other organizations', function (): void {
        $mine = $this->desk->ticket();
        $colleague = $this->desk->member(DefaultRole::Requester);

        $this->actingAs($colleague)->get(route('ticketing.portal.show', $mine))->assertForbidden();

        $outsider = $this->desk->member(DefaultRole::Requester, Organization::factory()->create());
        $this->actingAs($outsider)->get(route('ticketing.portal.show', $mine))->assertNotFound();
    });

    it('keeps working across Livewire requests (organization context is persistent)', function (): void {
        $ticket = $this->desk->ticket();

        // A real round trip: initial page, then an update request without route middleware.
        $this->actingAs($this->desk->requester);
        Livewire::withoutLazyLoading()->test(PortalShowTicket::class, ['ticket' => $ticket])
            ->set('reply', 'Any news?')
            ->call('sendReply')
            ->assertHasNoErrors();

        expect($ticket->comments()->where('body', 'Any news?')->exists())->toBeTrue();
    });

    it('lets the requester confirm a resolution', function (): void {
        $ticket = $this->desk->ticket();
        app(Modules\Ticketing\Application\Actions\ResolveTicket::class)->handle($ticket, Modules\Ticketing\Domain\Enums\ResolutionCode::Fixed, 'Done', $this->desk->agent);

        $this->actingAs($this->desk->requester);
        Livewire::test(PortalShowTicket::class, ['ticket' => $ticket])->call('close')->assertHasNoErrors();

        expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
    });
});

describe('ticketing', function (): void {
    it('is forbidden to requesters', function (): void {
        $this->actingAs($this->desk->requester)->get(route('ticketing.desk.index'))->assertForbidden();
        $this->actingAs($this->desk->requester)->get(route('ticketing.desk.show', $this->desk->ticket()))->assertForbidden();
    });

    it('lists open tickets, most urgent first, with filters and search', function (): void {
        $low = $this->desk->ticket(title: 'Mouse is slow');
        $critical = $this->desk->ticket(title: 'Email is down for everyone', impact: Modules\Ticketing\Domain\Enums\Impact::High, urgency: Modules\Ticketing\Domain\Enums\Urgency::High);
        app(AssignTicket::class)->handle($low, $this->desk->agent, null, $this->desk->lead);

        $this->actingAs($this->desk->agent);

        Livewire::test(TicketQueue::class)
            ->assertSeeInOrder([$critical->number, $low->number])
            ->set('assignee', 'me')
            ->assertSee($low->number)
            ->assertDontSee($critical->number)
            ->set('assignee', 'any')
            ->set('search', 'email')
            ->assertSee($critical->number)
            ->assertDontSee($low->number);
    });

    it('assigns, adds an internal note and resolves', function (): void {
        $ticket = $this->desk->ticket();
        $this->actingAs($this->desk->agent);

        Livewire::test(DeskShowTicket::class, ['ticket' => $ticket])
            ->call('assignToMe')
            ->set('comment', 'Checked the spooler')
            ->set('internal', true)
            ->call('addComment')
            ->set('resolutionCode', 'fixed')
            ->set('resolutionNote', 'Restarted the print spooler.')
            ->call('resolve')
            ->assertHasNoErrors();

        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::Resolved)
            ->and($ticket->assignee_id)->toBe($this->desk->agent->id)
            ->and($ticket->comments()->where('is_internal', true)->count())->toBe(1);

        Notification::assertSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'resolved');
    });

    it('refuses to assign someone outside the queue team', function (): void {
        $ticket = $this->desk->ticket();
        $outsider = $this->desk->member(DefaultRole::Agent);
        $this->actingAs($this->desk->lead);

        Livewire::test(DeskShowTicket::class, ['ticket' => $ticket])
            ->set('assigneeId', $outsider->id)
            ->call('assign')
            ->assertHasErrors(['assign']);

        expect($ticket->fresh()->assignee_id)->toBeNull();
    });

    it('rejects unknown status values sent from the browser', function (): void {
        $ticket = $this->desk->ticket();
        $this->actingAs($this->desk->agent);

        Livewire::test(DeskShowTicket::class, ['ticket' => $ticket])
            ->call('changeStatus', 'closed')
            ->assertHasErrors(['action']);

        expect($ticket->fresh()->status)->toBe(TicketStatus::New);
    });
});

describe('notifications', function (): void {
    it('never emails internal notes, nor people about their own actions', function (): void {
        $ticket = $this->desk->ticket();
        Notification::fake();

        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'internal only', internal: true);
        Notification::assertNothingSent();

        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'public', internal: false);
        Notification::assertSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'desk_replied');
        Notification::assertNotSentTo($this->desk->agent, TicketUpdate::class);
    });

    it('tells the assignee when the requester replies', function (): void {
        $ticket = $this->desk->ticket();
        app(AssignTicket::class)->handle($ticket, $this->desk->agent, null, $this->desk->lead);
        Notification::fake();

        app(AddTicketComment::class)->handle($ticket, $this->desk->requester, 'More info', internal: false);

        Notification::assertSentTo($this->desk->agent, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'requester_replied' && str_contains($n->url, '/desk/'));
        Notification::assertNotSentTo($this->desk->requester, TicketUpdate::class);
    });
});
