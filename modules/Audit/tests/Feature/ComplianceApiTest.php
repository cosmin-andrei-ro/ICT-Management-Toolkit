<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Modules\Audit\Application\Actions\AddFramework;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Audit\Tests\Support\ComplianceScenario;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Models\Risk;

beforeEach(function (): void {
    Notification::fake();
    $this->compliance = ComplianceScenario::create();
    $this->iso = app(AddFramework::class)->fromCatalog('iso27001');
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function complianceHeaders(User $user, array $abilities = ['compliance:read', 'compliance:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'compliance', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('reads frameworks and their requirements with their state', function (): void {
    $headers = complianceHeaders($this->compliance->auditor, ['compliance:read']);

    $this->getJson('/api/v1/compliance/frameworks', $headers)->assertOk()->assertJsonPath('data.0.key', 'iso27001')->assertJsonPath('data.0.requirements_count', 93);
    $this->getJson("/api/v1/compliance/frameworks/{$this->iso->id}/requirements?filter[search]=8.13", $headers)->assertOk()
        ->assertJsonPath('data.0.reference', '8.13')->assertJsonPath('data.0.state', 'undecided');
});

it('creates a control, maps it, and its owner records implementation, evidence and tests', function (): void {
    $manager = complianceHeaders($this->compliance->manager);
    $backups = Requirement::query()->where('framework_id', $this->iso->id)->where('reference', '8.13')->value('id');

    $id = $this->postJson('/api/v1/compliance/controls', ['name' => 'Backups and restore tests', 'owner_id' => $this->compliance->owner->id, 'test_interval_months' => 3], $manager)
        ->assertCreated()->assertJsonPath('data.reference', 'C-001')->json('data.id');
    $this->postJson("/api/v1/compliance/controls/{$id}/requirements", ['requirement_id' => $backups], $manager)->assertOk()->assertJsonPath('data.requirements.0.reference', '8.13');

    $owner = complianceHeaders($this->compliance->owner);
    $this->putJson("/api/v1/compliance/controls/{$id}/implementation", ['status' => 'implemented', 'implementation_notes' => 'Nightly to the NAS'], $owner)->assertOk()->assertJsonPath('data.status', 'implemented');
    $this->postJson("/api/v1/compliance/controls/{$id}/tests", ['tested_on' => now()->addDay()->toDateString(), 'result' => 'effective', 'method' => 'Restore'], $owner)->assertUnprocessable();
    $this->postJson("/api/v1/compliance/controls/{$id}/tests", ['tested_on' => now()->toDateString(), 'result' => 'effective', 'method' => 'Restored the file share'], $owner)->assertCreated()
        ->assertJsonPath('data.last_tested_on', now()->toDateString())->assertJsonPath('data.next_test_on', now()->addMonthsNoOverflow(3)->toDateString());

    $risk = Risk::factory()->create(['owner_id' => $this->compliance->owner->id]);
    $this->postJson("/api/v1/compliance/controls/{$id}/evidence", ['type' => 'risk.risk', 'id' => $risk->id], complianceHeaders($this->compliance->manager))->assertCreated()->assertJsonPath('data.evidence.0.linked_id', $risk->id);

    // The owner implements; only compliance managers change the control itself.
    $this->patchJson("/api/v1/compliance/controls/{$id}", ['name' => 'x'], complianceHeaders($this->compliance->owner))->assertForbidden();
    $this->getJson('/api/v1/compliance/controls?filter[framework]=iso27001', complianceHeaders($this->compliance->manager, ['compliance:read']))->assertJsonCount(1, 'data');
});

it('raises findings and tracks their corrective actions, without verifying them', function (): void {
    $manager = complianceHeaders($this->compliance->manager);

    $id = $this->postJson('/api/v1/compliance/findings', ['title' => 'Backups untested', 'description' => 'No restore test this year.', 'severity' => 'major_nonconformity', 'owner_id' => $this->compliance->owner->id], $manager)
        ->assertCreated()->assertJsonPath('data.status', 'open')->json('data.id');
    $this->patchJson("/api/v1/compliance/findings/{$id}", ['root_cause' => 'Nobody owned the tests'], $manager)->assertOk()->assertJsonPath('data.root_cause', 'Nobody owned the tests');

    $owner = complianceHeaders($this->compliance->owner);
    $actionId = $this->postJson("/api/v1/compliance/findings/{$id}/actions", ['title' => 'Quarterly restore tests', 'owner_id' => $this->compliance->owner->id], $owner)->assertCreated()->json('data.actions.0.id');
    $this->patchJson("/api/v1/compliance/findings/{$id}/actions/{$actionId}", ['status' => 'done'], $owner)->assertOk()->assertJsonPath('data.actions.0.status', 'done');

    $this->postJson("/api/v1/compliance/findings/{$id}/verify", [], $manager)->assertNotFound();
    expect(Finding::query()->findOrFail($id)->status->value)->toBe('open');
});

it('keeps to permissions and to the organization', function (): void {
    $control = Control::factory()->create();
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Control::factory()->create(['name' => 'Their control']));

    $this->getJson("/api/v1/compliance/controls/{$theirs->id}", complianceHeaders($this->compliance->manager))->assertNotFound();
    $this->getJson('/api/v1/compliance/controls', complianceHeaders($this->compliance->employee))->assertForbidden();
    $this->patchJson("/api/v1/compliance/controls/{$control->id}", ['name' => 'x'], complianceHeaders($this->compliance->auditor))->assertForbidden();
    $this->patchJson("/api/v1/compliance/controls/{$control->id}", ['name' => 'x'], complianceHeaders($this->compliance->manager, ['compliance:read']))->assertForbidden();
});
