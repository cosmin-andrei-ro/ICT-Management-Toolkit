<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    $this->apps = ApplicationsScenario::create();
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function applicationHeaders(User $user, array $abilities = ['applications:read', 'applications:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'applications', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('lists the portfolio and gives the documentation sheet in force', function (): void {
    Sheet::factory()->create(['application_id' => $this->apps->ticketing->id, 'status' => 'approved', 'major' => 1, 'minor' => 0, 'approved_at' => now(), 'content' => ['1_1' => ['description' => 'Service desk platform']]]);
    $headers = applicationHeaders($this->apps->manager, ['applications:read']);

    $this->getJson('/api/v1/applications?filter[search]=TKT', $headers)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'APP-TKT')->assertJsonPath('data.0.business_owner.id', $this->apps->businessOwner->id);
    $this->getJson("/api/v1/applications/{$this->apps->ticketing->id}/sheet", $headers)->assertOk()
        ->assertJsonPath('data.version', '1.0')->assertJsonPath('data.content.1_1.description', 'Service desk platform');

    $undocumented = Application::factory()->create(['code' => 'APP-NEW', 'business_owner_id' => $this->apps->businessOwner->id, 'technical_owner_id' => $this->apps->technicalOwner->id]);
    $this->getJson("/api/v1/applications/{$undocumented->id}/sheet", $headers)->assertNotFound();
});

it('registers and changes applications with unique codes and owners of the organization', function (): void {
    $headers = applicationHeaders($this->apps->manager);
    $fields = ['business_owner_id' => $this->apps->businessOwner->id, 'technical_owner_id' => $this->apps->technicalOwner->id];

    $this->postJson('/api/v1/applications', ['code' => 'app-tkt', 'name' => 'Another', ...$fields], $headers)->assertUnprocessable()->assertJsonValidationErrors('code');
    $id = $this->postJson('/api/v1/applications', ['code' => 'app-crm', 'name' => 'CRM', ...$fields], $headers)->assertCreated()->assertJsonPath('data.code', 'APP-CRM')->json('data.id');
    $this->patchJson("/api/v1/applications/{$id}", ['deputy_id' => User::factory()->create()->id], $headers)->assertUnprocessable()->assertJsonValidationErrors('deputy_id');
    $this->patchJson("/api/v1/applications/{$id}", ['name' => 'Customer relations'], $headers)->assertOk()->assertJsonPath('data.code', 'APP-CRM')->assertJsonPath('data.name', 'Customer relations');
});

it('keeps to permissions and to the organization', function (): void {
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Application::factory()->create());

    $this->getJson("/api/v1/applications/{$theirs->id}", applicationHeaders($this->apps->manager))->assertNotFound();
    $this->getJson('/api/v1/applications', applicationHeaders($this->apps->employee))->assertForbidden();
    // The business owner reads their application but does not manage the portfolio.
    $this->getJson("/api/v1/applications/{$this->apps->ticketing->id}", applicationHeaders($this->apps->businessOwner))->assertOk();
    $this->patchJson("/api/v1/applications/{$this->apps->ticketing->id}", ['name' => 'x'], applicationHeaders($this->apps->businessOwner))->assertForbidden();
});
