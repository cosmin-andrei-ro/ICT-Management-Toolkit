<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Notifications\TicketUpdate;
use Modules\Ticketing\Application\Problems\CreateProblem;
use Modules\Ticketing\Application\Problems\LinkIncidentToProblem;
use Modules\Ticketing\Application\Problems\MarkKnownError;
use Modules\Ticketing\Application\Problems\ResolveProblem;
use Modules\Ticketing\Application\Problems\UpdateProblemAnalysis;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
});

function newProblem(TicketingScenario $desk, array $incidents = []): Ticket
{
    return app(CreateProblem::class)->handle('Mail server overloaded', 'Many users report slow email.', Impact::High, Urgency::Medium, $desk->agent, $incidents);
}

it('opens a problem from incidents, numbered separately and without SLA clocks', function (): void {
    $first = $this->desk->ticket(title: 'Email is slow');
    $second = $this->desk->ticket(title: 'Outlook hangs');

    $problem = newProblem($this->desk, [$first, $second]);

    expect($problem->type)->toBe(TicketType::Problem)
        ->and($problem->number)->toBe('PRB-000001')
        ->and($problem->problemDetails)->not->toBeNull()
        ->and($problem->incidents()->pluck('id')->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and(SlaTimer::query()->where('ticket_id', $problem->id)->count())->toBe(0);
});

it('refuses to link anything but incidents, and only to open problems of the organization', function (): void {
    $problem = newProblem($this->desk);
    $request = $this->desk->ticket(type: TicketType::ServiceRequest);
    $foreignProblem = app(OrganizationContext::class)->runAs(
        Organization::factory()->create(),
        fn () => Ticket::factory()->create(['type' => TicketType::Problem, 'number' => 'PRB-999999']),
    );

    expect(fn () => app(LinkIncidentToProblem::class)->handle($request, $problem, $this->desk->agent))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(LinkIncidentToProblem::class)->handle($this->desk->ticket(), $foreignProblem, $this->desk->agent))->toThrow(InvalidArgumentException::class);
});

it('only lets incidents point at problems in the database', function (): void {
    $incident = $this->desk->ticket();
    $other = $this->desk->ticket();

    DB::table('ticketing_tickets')->where('id', $incident->id)->update(['problem_id' => $other->id]);
})->throws(QueryException::class, 'must reference a problem');

it('only lets incidents carry a problem link in the database', function (): void {
    $problem = newProblem($this->desk);
    $request = $this->desk->ticket(type: TicketType::ServiceRequest);

    DB::table('ticketing_tickets')->where('id', $request->id)->update(['problem_id' => $problem->id]);
})->throws(QueryException::class, 'ticketing_tickets_problem_only_on_incidents');

it('becomes a known error only with a workaround, and hands it to agents linking incidents', function (): void {
    $problem = newProblem($this->desk);

    expect(fn () => app(MarkKnownError::class)->handle($problem->fresh()))->toThrow(InvalidArgumentException::class);

    app(UpdateProblemAnalysis::class)->handle($problem->fresh(), 'Mailbox database on a full disk.', 'Restart the Outlook cache: File > Account settings > Repair.');
    app(MarkKnownError::class)->handle($problem->fresh());

    expect($problem->fresh()->problemDetails?->isKnownError())->toBeTrue();

    $incident = $this->desk->ticket();
    app(LinkIncidentToProblem::class)->handle($incident, $problem->fresh(), $this->desk->agent);

    $note = Comment::query()->where('commentable_id', $incident->id)->sole();
    expect($note->is_internal)->toBeTrue()
        ->and($note->body)->toContain('PRB-000001')->toContain('Repair');

    expect(fn () => app(UpdateProblemAnalysis::class)->handle($problem->fresh(), 'x', ''))->toThrow(InvalidArgumentException::class);
});

it('resolves linked open incidents with the problem when asked, notifying their requesters', function (): void {
    $open = $this->desk->ticket();
    $alreadyResolved = $this->desk->ticket();
    $problem = newProblem($this->desk, [$open, $alreadyResolved]);
    app(Modules\Ticketing\Application\Actions\ResolveTicket::class)->handle($alreadyResolved, ResolutionCode::Workaround, 'Restarted', $this->desk->agent);
    Notification::fake();

    $count = app(ResolveProblem::class)->handle($problem->fresh(), ResolutionCode::Fixed, 'Disk extended.', true, $this->desk->agent);

    expect($count)->toBe(1)
        ->and($problem->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($open->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($open->fresh()->resolution_code)->toBe(ResolutionCode::Fixed)
        ->and($alreadyResolved->fresh()->resolution_code)->toBe(ResolutionCode::Workaround);

    Notification::assertSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'resolved' && $n->number === $open->number);
});

it('leaves incidents alone when propagation is off', function (): void {
    $open = $this->desk->ticket();
    $problem = newProblem($this->desk, [$open]);

    app(ResolveProblem::class)->handle($problem->fresh(), ResolutionCode::Fixed, 'Disk extended.', false, $this->desk->agent);

    expect($open->fresh()->status)->toBe(TicketStatus::New);
});

it('keeps problems out of the portal and away from requesters', function (): void {
    $problem = newProblem($this->desk);

    expect(Ticket::query()->visibleToRequester($this->desk->agent)->pluck('id')->all())->not->toContain($problem->id)
        ->and(Gate::forUser($this->desk->requester)->allows('createProblem'))->toBeFalse()
        ->and(Gate::forUser($this->desk->requester)->allows('manageProblem', $problem))->toBeFalse()
        ->and(Gate::forUser($this->desk->agent)->allows('manageProblem', $problem))->toBeTrue();
});

it('does not let requesters raise problems through the portal or the API', function (): void {
    $this->actingAs($this->desk->requester);

    Livewire\Livewire::test(Modules\Ticketing\Http\Livewire\Portal\CreateTicket::class)
        ->set('type', 'problem')->set('title', 'Sneaky problem')->set('description', 'x')
        ->call('save')
        ->assertHasErrors(['type']);

    $token = app(Modules\Core\Application\Api\IssueApiToken::class)->handle($this->desk->requester, 'x', ['tickets:write']);
    auth()->forgetGuards();

    $this->postJson('/api/v1/tickets', ['type' => 'problem', 'title' => 'Sneaky problem', 'description' => 'x'], ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});
