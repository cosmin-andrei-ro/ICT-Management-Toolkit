<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Catalog\ProvisionCatalog;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Tests\Support\RiskScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->risks = RiskScenario::create();
    app(ProvisionCatalog::class)->handle();
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function riskHeaders(User $user, array $abilities = ['risks:read', 'risks:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'risks', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('registers a risk, which its owner assesses and treats, with what it concerns and its actions', function (): void {
    $manager = riskHeaders($this->risks->manager);

    $this->postJson('/api/v1/risks', ['title' => 'Payroll stops', 'category' => 'operational', 'owner_id' => (string) Str::uuid()], $manager)->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    $id = $this->postJson('/api/v1/risks', ['title' => 'Payroll stops', 'category' => 'operational', 'owner_id' => $this->risks->owner->id], $manager)
        ->assertCreated()->assertJsonPath('data.reference', 'R-0001')->assertJsonPath('data.inherent.score', null)->json('data.id');

    $owner = riskHeaders($this->risks->owner, ['risks:read', 'risks:write']);
    $threat = CatalogEntry::query()->where('name', 'Supplier failure')->value('id');
    $this->putJson("/api/v1/risks/{$id}/assessment", ['likelihood' => 4, 'impact' => 5, 'catalog_entry_ids' => [$threat], 'existing_controls' => 'Weekly backup'], $owner)->assertOk()
        ->assertJsonPath('data.inherent.score', 20)->assertJsonPath('data.inherent.level', 'critical')->assertJsonPath('data.catalog_entries.0.name', 'Supplier failure');
    $this->putJson("/api/v1/risks/{$id}/treatment", ['treatment' => 'mitigate', 'treatment_plan' => 'Second supplier', 'residual_likelihood' => 2, 'residual_impact' => 3], $owner)->assertOk()
        ->assertJsonPath('data.residual.score', 6)->assertJsonPath('data.treatment', 'mitigate');
    $this->postJson("/api/v1/risks/{$id}/assets", ['type' => 'supplier', 'id' => $this->risks->vendor->id], $owner)->assertNoContent();
    $actionId = $this->postJson("/api/v1/risks/{$id}/actions", ['title' => 'Sign a backup contract', 'owner_id' => $this->risks->owner->id, 'due_on' => now()->addMonth()->toDateString()], $owner)
        ->assertCreated()->json('data.actions.0.id');
    $this->patchJson("/api/v1/risks/{$id}/actions/{$actionId}", ['status' => 'done'], $owner)->assertOk()->assertJsonPath('data.actions.0.status', 'done');

    $this->getJson("/api/v1/risks/{$id}", $owner)->assertOk()->assertJsonPath('data.assets.0.name', 'Payroll Outsourcing SRL');
    // The owner assesses; only risk managers change the register.
    $this->patchJson("/api/v1/risks/{$id}", ['title' => 'x'], $owner)->assertForbidden();
});

it('lists the register with filters, and the catalog', function (): void {
    Risk::factory()->create(['title' => 'Ransomware', 'category' => 'information_security', 'owner_id' => $this->risks->owner->id]);
    Risk::factory()->create(['title' => 'Supplier exit', 'category' => 'supplier', 'owner_id' => $this->risks->owner->id]);
    $headers = riskHeaders($this->risks->manager, ['risks:read']);

    $this->getJson('/api/v1/risks?filter[category]=supplier', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Supplier exit');
    $this->getJson('/api/v1/risks?filter[search]=ransom&sort=-title', $headers)->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/risks/catalog', $headers)->assertOk()->assertJsonFragment(['name' => 'Supplier failure', 'kind' => 'threat']);
});

it('keeps to permissions and to the organization; acceptances are not exposed', function (): void {
    $risk = Risk::factory()->create(['owner_id' => $this->risks->owner->id]);
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Risk::factory()->create(['title' => 'Their risk']));

    $this->getJson("/api/v1/risks/{$theirs->id}", riskHeaders($this->risks->manager))->assertNotFound();
    $this->getJson('/api/v1/risks', riskHeaders($this->risks->employee))->assertForbidden();
    $this->putJson("/api/v1/risks/{$risk->id}/assessment", ['likelihood' => 2, 'impact' => 2], riskHeaders($this->risks->employee))->assertForbidden();
    $this->putJson("/api/v1/risks/{$risk->id}/assessment", ['likelihood' => 2, 'impact' => 2], riskHeaders($this->risks->manager, ['risks:read']))->assertForbidden();
    $this->postJson("/api/v1/risks/{$risk->id}/acceptance", [], riskHeaders($this->risks->owner))->assertNotFound();
});
