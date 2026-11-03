<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
    $token = app(IssueApiToken::class)->handle($this->desk->agent, 'conventions', ['tickets:read']);
    $this->headers = ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
});

it('filters, sorts and pages lists the same way everywhere', function (): void {
    $this->desk->ticket(title: 'Alpha incident');
    $this->desk->ticket(title: 'Beta request', type: TicketType::ServiceRequest);
    $this->desk->ticket(title: 'Gamma urgent', impact: Impact::High, urgency: Urgency::High);

    $this->getJson('/api/v1/tickets?filter[type]=service_request', $this->headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Beta request');
    $this->getJson('/api/v1/tickets?sort=number&per_page=2', $this->headers)->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'Alpha incident')->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 2);
    $this->getJson('/api/v1/tickets?filter[type]=incident&sort=-number', $this->headers)->assertJsonPath('data.0.title', 'Gamma urgent');
    $this->getJson('/api/v1/tickets?filter[search]=Gamma', $this->headers)->assertJsonCount(1, 'data');

    // The first version's parameters keep working.
    $this->getJson('/api/v1/tickets?search=Alpha&status=open', $this->headers)->assertOk()->assertJsonCount(1, 'data');
});

it('refuses what it does not know, naming the parameter', function (): void {
    $this->getJson('/api/v1/tickets?filter[colour]=red', $this->headers)->assertUnprocessable()->assertJsonValidationErrors('filter');
    $this->getJson('/api/v1/tickets?filter[type]=nonsense', $this->headers)->assertUnprocessable()->assertJsonValidationErrors('filter');
    $this->getJson('/api/v1/tickets?sort=password', $this->headers)->assertUnprocessable()->assertJsonValidationErrors('sort');
    $this->getJson('/api/v1/tickets?per_page=500', $this->headers)->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/v1/tickets?per_page=0', $this->headers)->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('works in the organization the token was issued for, whatever the user switches to in the browser', function (): void {
    $this->desk->ticket(title: 'Ticket of the first company');
    $other = Organization::factory()->create();
    $this->desk->agent->organizations()->attach($other);
    app(OrganizationContext::class)->runAs($other, function () use ($other): void {
        $this->desk->agent->assignRole(DefaultRole::Agent->value);
        $requester = $this->desk->member(DefaultRole::Requester, $other);
        $this->desk->ticket(requester: $requester, title: 'Ticket of the second company');
    });

    $this->desk->agent->forceFill(['current_organization_id' => $other->id])->save();

    $this->getJson('/api/v1/tickets', $this->headers)->assertOk()
        ->assertJsonFragment(['title' => 'Ticket of the first company'])->assertJsonMissing(['title' => 'Ticket of the second company']);

    // Leaving the organization stops the token there.
    $this->desk->agent->organizations()->detach($this->desk->organization->id);
    auth()->forgetGuards();
    $this->getJson('/api/v1/tickets', $this->headers)->assertForbidden();
});
