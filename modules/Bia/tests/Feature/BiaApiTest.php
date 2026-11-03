<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Tests\Support\BiaScenario;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    $this->bia = BiaScenario::create();
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function biaHeaders(User $user, array $abilities = ['bia:read', 'bia:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'bia', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('reads processes with their recovery objectives', function (): void {
    $this->bia->payroll->forceFill(['tier' => 1, 'rto_minutes' => 240, 'rpo_minutes' => 60, 'approved_at' => now()])->save();
    BusinessProcess::factory()->create(['name' => 'Recruitment', 'owner_id' => $this->bia->owner->id]);
    $headers = biaHeaders($this->bia->coordinator, ['bia:read']);

    $this->getJson('/api/v1/bia/processes?filter[approved]=true', $headers)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Payroll')->assertJsonPath('data.0.tier', 1)->assertJsonPath('data.0.rto_minutes', 240)->assertJsonPath('data.0.department.name', 'Finance');
    $this->getJson('/api/v1/bia/processes?sort=-name', $headers)->assertJsonPath('data.0.name', 'Recruitment');
});

it('registers and changes processes with the screen\'s rules', function (): void {
    $headers = biaHeaders($this->bia->coordinator);

    $this->postJson('/api/v1/bia/processes', ['name' => 'payroll', 'owner_id' => $this->bia->owner->id], $headers)->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/v1/bia/processes', ['name' => 'Invoicing', 'owner_id' => User::factory()->create()->id], $headers)->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    $id = $this->postJson('/api/v1/bia/processes', ['name' => 'Invoicing', 'owner_id' => $this->bia->owner->id, 'peak_periods' => 'Month end'], $headers)
        ->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonPath('data.tier', null)->json('data.id');

    $this->patchJson("/api/v1/bia/processes/{$id}", ['status' => 'retired'], $headers)->assertOk()->assertJsonPath('data.status', 'retired')->assertJsonPath('data.peak_periods', 'Month end');
});

it('keeps to permissions and to the organization', function (): void {
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => BusinessProcess::factory()->create());

    $this->getJson("/api/v1/bia/processes/{$theirs->id}", biaHeaders($this->bia->coordinator))->assertNotFound();
    $this->getJson('/api/v1/bia/processes', biaHeaders($this->bia->employee))->assertForbidden();
    // The owner sees their process but does not manage the register.
    $this->getJson("/api/v1/bia/processes/{$this->bia->payroll->id}", biaHeaders($this->bia->owner))->assertOk();
    $this->patchJson("/api/v1/bia/processes/{$this->bia->payroll->id}", ['name' => 'x'], biaHeaders($this->bia->owner))->assertForbidden();
});
