<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function bearer(User $user, array $abilities = ['tickets:read', 'tickets:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'test', $abilities);

    // Each request must resolve the user from the token, not from an earlier request.
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('requires a token', function (): void {
    $this->getJson('/api/v1/tickets')->assertUnauthorized();
});

it('issues prefixed tokens that expire, and audits issuance', function (): void {
    $token = app(IssueApiToken::class)->handle($this->desk->requester, 'CI', ['tickets:read']);

    expect($token->plainTextToken)->toMatch('/^\d+\|ictmt_/')
        ->and($token->accessToken->expires_at)->not->toBeNull()
        ->and(Modules\Core\Domain\Audit\AuditLog::query()->where('auditable_id', $this->desk->requester->id)->latest('created_at')->latest('id')->first()?->new_values)
        ->toBe(['api_token_issued' => ['name' => 'CI', 'abilities' => ['tickets:read'], 'organization' => $this->desk->organization->id]]);
});

it('refuses unknown abilities', function (): void {
    app(IssueApiToken::class)->handle($this->desk->requester, 'x', ['admin:*']);
})->throws(InvalidArgumentException::class);

it('creates a ticket through the API', function (): void {
    $this->postJson('/api/v1/tickets', [
        'type' => 'service_request',
        'title' => 'New laptop for onboarding',
        'description' => 'Starts on Monday.',
        'urgency' => 'medium',
    ], bearer($this->desk->requester))
        ->assertCreated()
        ->assertJsonPath('data.number', 'REQ-000001')
        ->assertJsonPath('data.channel', 'api')
        ->assertJsonPath('data.requester.id', $this->desk->requester->id);

    expect(Ticket::query()->sole()->channel)->toBe(TicketChannel::Api);
});

it('validates input', function (): void {
    $this->postJson('/api/v1/tickets', ['type' => 'problem', 'title' => 'x'], bearer($this->desk->requester))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'title', 'description']);
});

it('enforces token abilities on top of user permissions', function (): void {
    $this->postJson('/api/v1/tickets', ['type' => 'incident', 'title' => 'Read-only token', 'description' => 'x'], bearer($this->desk->requester, ['tickets:read']))
        ->assertForbidden();
});

it('lists only what the user may see', function (): void {
    $mine = $this->desk->ticket();
    $theirs = $this->desk->ticket($this->desk->member(DefaultRole::Requester));

    $this->getJson('/api/v1/tickets', bearer($this->desk->requester))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->getJson('/api/v1/tickets', bearer($this->desk->agent))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/v1/tickets/{$theirs->id}", bearer($this->desk->requester))->assertForbidden();
});

it('never exposes internal notes to requesters', function (): void {
    $ticket = $this->desk->ticket();
    app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'public reply', internal: false);
    app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'internal note', internal: true);

    $this->getJson("/api/v1/tickets/{$ticket->id}", bearer($this->desk->requester))
        ->assertOk()
        ->assertJsonCount(1, 'data.comments')
        ->assertJsonMissing(['body' => 'internal note']);

    $this->getJson("/api/v1/tickets/{$ticket->id}", bearer($this->desk->agent))
        ->assertJsonCount(2, 'data.comments');
});

it('lets only IT support staff post internal notes', function (): void {
    $ticket = $this->desk->ticket();

    $this->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'sneaky', 'internal' => true], bearer($this->desk->requester))
        ->assertForbidden();

    $this->postJson("/api/v1/tickets/{$ticket->id}/comments", ['body' => 'checking', 'internal' => true], bearer($this->desk->agent))
        ->assertCreated()
        ->assertJsonPath('data.internal', true);
});

it('hides tickets of other organizations', function (): void {
    $ticket = $this->desk->ticket();
    $outsider = $this->desk->member(DefaultRole::Agent, Organization::factory()->create());

    $this->getJson("/api/v1/tickets/{$ticket->id}", bearer($outsider))->assertNotFound();
    $this->getJson('/api/v1/tickets', bearer($outsider))->assertJsonCount(0, 'data');
});

it('rejects tokens of deactivated users', function (): void {
    $headers = bearer($this->desk->requester);
    $this->desk->requester->forceFill(['is_active' => false])->save();

    $this->getJson('/api/v1/tickets', $headers)->assertForbidden();
});

it('serves the OpenAPI document to administrators only', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $admin = $this->desk->member(DefaultRole::Administrator);

    $this->actingAs($this->desk->requester)->get('/docs/api/openapi.json')->assertForbidden();
    $this->actingAs($admin)->get('/docs/api/openapi.json')->assertOk()->assertJsonPath('info.title', 'ICT Management Toolkit API');
    $this->get('/docs/api')->assertNotFound();
});
