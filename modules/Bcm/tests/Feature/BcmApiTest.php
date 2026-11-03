<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Tests\Support\BcmScenario;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->bcm = BcmScenario::create();
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function bcmHeaders(User $user, array $abilities = ['continuity:read', 'continuity:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'bcm', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('shows the plan in force: activation, team and recovery steps', function (): void {
    $plan = $this->bcm->approvedPayrollDrp();
    Exercise::factory()->create(['plan_id' => $plan->id, 'title' => 'Restore test']);
    $headers = bcmHeaders($this->bcm->coordinator, ['continuity:read']);

    $this->getJson("/api/v1/continuity/plans/{$plan->id}", $headers)->assertOk()
        ->assertJsonPath('data.approved_version', 1)
        ->assertJsonPath('data.in_force.activation_criteria', 'Payroll application down for more than 1 hour')
        ->assertJsonPath('data.in_force.team.0.role', 'Incident manager')
        ->assertJsonPath('data.in_force.steps.1.action', 'Restore the database from backup')
        ->assertJsonPath('data.in_force.estimated_recovery_minutes', 180)
        ->assertJsonPath('data.in_force.process_ids.0', $this->bcm->payroll->id);
    $this->getJson('/api/v1/continuity/plans?filter[approved]=true', $headers)->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/continuity/exercises?filter[plan_id]={$plan->id}", $headers)->assertOk()->assertJsonPath('data.0.title', 'Restore test');
    $this->getJson('/api/v1/continuity/activations', $headers)->assertOk()->assertJsonCount(0, 'data');
});

it('creates and changes plans; a team lead only recovery plans', function (): void {
    $id = $this->postJson('/api/v1/continuity/plans', ['type' => 'bcp', 'name' => 'Office loss', 'owner_id' => $this->bcm->owner->id], bcmHeaders($this->bcm->coordinator))
        ->assertCreated()->assertJsonPath('data.type', 'bcp')->assertJsonPath('data.approved_version', null)->json('data.id');
    $this->patchJson("/api/v1/continuity/plans/{$id}", ['description' => 'Head office unavailable'], bcmHeaders($this->bcm->coordinator))->assertOk()->assertJsonPath('data.name', 'Office loss');

    $lead = bcmHeaders($this->bcm->lead);
    $this->postJson('/api/v1/continuity/plans', ['type' => 'bcp', 'name' => 'Warehouse', 'owner_id' => $this->bcm->owner->id], $lead)->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->postJson('/api/v1/continuity/plans', ['type' => 'drp', 'name' => 'Mail DRP', 'owner_id' => $this->bcm->lead->id], $lead)->assertCreated();
});

it('keeps to permissions and to the organization', function (): void {
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Plan::factory()->create());

    $this->getJson("/api/v1/continuity/plans/{$theirs->id}", bcmHeaders($this->bcm->coordinator))->assertNotFound();
    $this->getJson('/api/v1/continuity/plans', bcmHeaders($this->bcm->employee))->assertForbidden();
    $this->postJson('/api/v1/continuity/plans', ['type' => 'drp', 'name' => 'x', 'owner_id' => $this->bcm->owner->id], bcmHeaders($this->bcm->coordinator, ['continuity:read']))->assertForbidden();
});
