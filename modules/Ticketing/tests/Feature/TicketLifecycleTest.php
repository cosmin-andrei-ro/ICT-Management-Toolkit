<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\AutoCloseResolvedTickets;
use Modules\Ticketing\Application\Actions\CancelTicket;
use Modules\Ticketing\Application\Actions\ChangeTicketStatus;
use Modules\Ticketing\Application\Actions\CloseTicket;
use Modules\Ticketing\Application\Actions\ReopenTicket;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Actions\TicketNotOpen;
use Modules\Ticketing\Application\Actions\UpdateTicketDetails;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Events\TicketAssigned;
use Modules\Ticketing\Domain\Events\TicketCreated;
use Modules\Ticketing\Domain\Events\TicketResolved;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    $this->desk = TicketingScenario::create();
});

describe('creating', function (): void {
    it('numbers tickets per organization and type, in the default queue', function (): void {
        Event::fake([TicketCreated::class]);

        $first = $this->desk->ticket();
        $second = $this->desk->ticket();
        $request = $this->desk->ticket(type: TicketType::ServiceRequest);

        expect([$first->number, $second->number, $request->number])->toBe(['INC-000001', 'INC-000002', 'REQ-000001'])
            ->and($first->status)->toBe(TicketStatus::New)
            ->and($first->queue_id)->toBe($this->desk->queue->id)
            ->and($first->statusTransitions()->sole()->to_status)->toBe(TicketStatus::New);

        Event::assertDispatchedTimes(TicketCreated::class, 3);
    });

    it('restarts numbering in another organization', function (): void {
        $this->desk->ticket();
        $other = Organization::factory()->create();
        $requester = $this->desk->member(DefaultRole::Requester, $other);

        $ticket = app(OrganizationContext::class)->runAs($other, fn () => $this->desk->ticket($requester));

        expect($ticket->number)->toBe('INC-000001');
    });

    it('computes priority from the matrix, with organization overrides', function (): void {
        expect($this->desk->ticket(impact: Impact::High, urgency: Urgency::High)->priority)->toBe(Priority::Critical);

        DB::table('ticketing_priority_matrix')->insert([
            'organization_id' => $this->desk->organization->id, 'impact' => 'low', 'urgency' => 'low', 'priority' => 'medium',
        ]);

        expect($this->desk->ticket()->priority)->toBe(Priority::Medium);
    });

    it('refuses requesters from another organization', function (): void {
        $outsider = $this->desk->member(DefaultRole::Requester, Organization::factory()->create());

        $this->desk->ticket($outsider);
    })->throws(InvalidArgumentException::class);

    it('finds tickets by number and words', function (): void {
        $printer = $this->desk->ticket(title: 'Printer jammed on floor 2');
        $vpn = $this->desk->ticket(title: 'VPN disconnects every hour');

        expect(Ticket::query()->search('vpn')->pluck('id')->all())->toBe([$vpn->id])
            ->and(Ticket::query()->search('printers')->pluck('id')->all())->toBe([$printer->id])
            ->and(Ticket::query()->search($printer->number)->pluck('id')->all())->toBe([$printer->id]);
    });
});

describe('assigning', function (): void {
    it('assigns to a team member and moves the ticket to assigned', function (): void {
        Event::fake([TicketAssigned::class]);
        $ticket = $this->desk->ticket();

        app(AssignTicket::class)->handle($ticket, $this->desk->agent, null, $this->desk->lead);

        expect($ticket->fresh())
            ->status->toBe(TicketStatus::Assigned)
            ->assignee_id->toBe($this->desk->agent->id);
        Event::assertDispatched(TicketAssigned::class);
    });

    it('refuses assignees who cannot work tickets or are outside the queue team', function (): void {
        $ticket = $this->desk->ticket();
        $agentElsewhere = $this->desk->member(DefaultRole::Agent);

        expect(fn () => app(AssignTicket::class)->handle($ticket, $this->desk->requester, null, $this->desk->lead))
            ->toThrow(InvalidArgumentException::class, 'cannot work tickets')
            ->and(fn () => app(AssignTicket::class)->handle($ticket, $agentElsewhere, null, $this->desk->lead))
            ->toThrow(InvalidArgumentException::class, 'queue\'s team');
    });

    it('cannot route to a queue of another organization', function (): void {
        $ticket = $this->desk->ticket();
        $foreignQueue = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Queue::factory()->create());

        app(AssignTicket::class)->handle($ticket, null, $foreignQueue, $this->desk->lead);
    })->throws(InvalidArgumentException::class);

    it('goes back to new when unassigned', function (): void {
        $ticket = $this->desk->ticket();
        app(AssignTicket::class)->handle($ticket, $this->desk->agent, null, $this->desk->lead);

        app(AssignTicket::class)->handle($ticket, null, null, $this->desk->lead);

        expect($ticket->fresh()->status)->toBe(TicketStatus::New);
    });
});

describe('working and resolving', function (): void {
    it('takes an unowned ticket when work starts', function (): void {
        $ticket = $this->desk->ticket();

        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::InProgress, $this->desk->agent);

        expect($ticket->fresh())
            ->status->toBe(TicketStatus::InProgress)
            ->assignee_id->toBe($this->desk->agent->id);
    });

    it('resolves with a code and a public note, then the requester closes it', function (): void {
        Event::fake([TicketResolved::class]);
        $ticket = $this->desk->ticket();

        app(ResolveTicket::class)->handle($ticket, ResolutionCode::Fixed, 'Replaced the toner.', $this->desk->agent);

        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::Resolved)
            ->and($ticket->resolution_code)->toBe(ResolutionCode::Fixed)
            ->and($ticket->resolved_at)->not->toBeNull()
            ->and(Comment::query()->where('commentable_id', $ticket->id)->sole()->is_internal)->toBeFalse();
        Event::assertDispatched(TicketResolved::class);

        app(CloseTicket::class)->handle($ticket, $this->desk->requester);

        expect($ticket->fresh())->status->toBe(TicketStatus::Closed)->closed_at->not->toBeNull();
    });

    it('reopens a resolved ticket to its assignee and clears the resolution', function (): void {
        $ticket = $this->desk->ticket();
        app(ResolveTicket::class)->handle($ticket, ResolutionCode::Fixed, '', $this->desk->agent);

        app(ReopenTicket::class)->handle($ticket, 'Still offline.', $this->desk->requester);

        expect($ticket->fresh())
            ->status->toBe(TicketStatus::InProgress)
            ->resolved_at->toBeNull()
            ->resolution_code->toBeNull()
            ->reopened_count->toBe(1);
    });

    it('rejects changes to final tickets', function (): void {
        $ticket = $this->desk->ticket();
        app(CancelTicket::class)->handle($ticket, 'Logged twice.', $this->desk->requester);

        expect(fn () => app(AddTicketComment::class)->handle($ticket, $this->desk->requester, 'Hello?', internal: false))
            ->toThrow(TicketNotOpen::class)
            ->and(fn () => app(AssignTicket::class)->handle($ticket, $this->desk->agent, null, $this->desk->lead))
            ->toThrow(TicketNotOpen::class);
    });

    it('records the first response and resumes pending tickets when the requester replies', function (): void {
        $ticket = $this->desk->ticket();
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::InProgress, $this->desk->agent);

        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'Internal: checking logs', internal: true);
        expect($ticket->fresh()->first_responded_at)->toBeNull();

        app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'Can you send a photo of the display?', internal: false);
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Pending, $this->desk->agent);
        expect($ticket->fresh()->first_responded_at)->not->toBeNull();

        app(AddTicketComment::class)->handle($ticket, $this->desk->requester, 'Here it is.', internal: false);
        expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
    });

    it('recomputes priority on triage', function (): void {
        $ticket = $this->desk->ticket();

        app(UpdateTicketDetails::class)->handle($ticket, 'All printers offline', 'Whole building.', Impact::High, Urgency::Medium, null);

        expect($ticket->fresh()->priority)->toBe(Priority::High);
    });

    it('audits ticket changes', function (): void {
        $ticket = $this->desk->ticket();
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::InProgress, $this->desk->agent);

        $update = AuditLog::query()->where('auditable_type', 'ticketing.ticket')->where('event', AuditEvent::Updated)->latest('created_at')->first();

        expect($update?->new_values)->toMatchArray(['status' => 'in_progress', 'assignee_id' => $this->desk->agent->id])
            ->and($update?->new_values)->not->toHaveKey('search_vector');
    });
});

describe('automatic closing', function (): void {
    it('closes tickets resolved longer than the grace period, across organizations', function (): void {
        $old = $this->desk->ticket();
        $recent = $this->desk->ticket();
        app(ResolveTicket::class)->handle($old, ResolutionCode::Fixed, '', $this->desk->agent);
        app(ResolveTicket::class)->handle($recent, ResolutionCode::Fixed, '', $this->desk->agent);
        $old->forceFill(['resolved_at' => now()->subDays(6)])->save();

        app(OrganizationContext::class)->clear();
        expect(app(AutoCloseResolvedTickets::class)->handle())->toBe(1);

        app(OrganizationContext::class)->set($this->desk->organization);
        expect($old->fresh()->status)->toBe(TicketStatus::Closed)
            ->and($recent->fresh()->status)->toBe(TicketStatus::Resolved);
    });

    it('can be disabled', function (): void {
        config()->set('ticketing.tickets.auto_close_after_days', 0);

        expect(app(AutoCloseResolvedTickets::class)->handle())->toBe(0);
    });
});

describe('database invariants', function (): void {
    it('requires an assignee for work in progress', function (): void {
        $ticket = $this->desk->ticket();

        DB::table('ticketing_tickets')->where('id', $ticket->id)->update(['status' => 'in_progress']);
    })->throws(QueryException::class, 'ticketing_tickets_assignee_required');

    it('requires a resolution for resolved tickets', function (): void {
        $ticket = $this->desk->ticket();

        DB::table('ticketing_tickets')->where('id', $ticket->id)->update(['status' => 'resolved']);
    })->throws(QueryException::class, 'ticketing_tickets_resolution_required');

    it('rejects a queue of another organization', function (): void {
        $ticket = $this->desk->ticket();
        $foreignQueue = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Queue::factory()->create());

        DB::table('ticketing_tickets')->where('id', $ticket->id)->update(['queue_id' => $foreignQueue->id]);
    })->throws(QueryException::class, 'ticketing_tickets_queue_fk');
});
