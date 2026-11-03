<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Modules\Audit\Application\Webhooks\AuditWebhooks;
use Modules\Audit\Domain\Models\Finding;
use Modules\Bcm\Application\Webhooks\BcmWebhooks;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Cmdb\Application\Webhooks\CmdbWebhooks;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Domain\Models\WebhookDelivery;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Itam\Application\Webhooks\ItamWebhooks;
use Modules\Itam\Domain\Models\Asset;
use Modules\Risk\Application\Webhooks\RiskWebhooks;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\ScoreRecord;

beforeEach(function (): void {
    Queue::fake();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->person = User::factory()->memberOf($this->organization)->create();
    Webhook::factory()->create(['events' => array_keys(app(WebhookEvents::class)->all())]);
});

/**
 * @return list<array{event: string, data: array<string, mixed>}>
 */
function publishedWebhooks(): array
{
    return WebhookDelivery::query()->orderBy('id')->get()
        ->map(fn (WebhookDelivery $delivery): array => ['event' => $delivery->event, 'data' => $delivery->payload['data']])
        ->all();
}

it('offers the events of every module to webhooks', function (): void {
    expect(array_keys(app(WebhookEvents::class)->all()))->toContain(
        ...array_keys(CmdbWebhooks::EVENTS), ...array_keys(ItamWebhooks::EVENTS), ...array_keys(RiskWebhooks::EVENTS),
        ...array_keys(AuditWebhooks::EVENTS), ...array_keys(BcmWebhooks::EVENTS),
    );
});

it('publishes configuration items created, changed and deleted, naming the fields only', function (): void {
    $item = ConfigurationItem::factory()->create(['name' => 'srv-db-01', 'identifier' => 'SRV-01']);
    $item->update(['description' => 'Holds the payroll database']);
    $item->touch();
    $item->delete();

    $sent = publishedWebhooks();
    expect(array_column($sent, 'event'))->toBe(['cmdb.item.created', 'cmdb.item.updated', 'cmdb.item.deleted'])
        ->and($sent[0]['data'])->toEqual(['id' => $item->id, 'reference' => 'SRV-01', 'title' => 'srv-db-01', 'url' => route('api.v1.cmdb.items.show', $item->id)])
        ->and($sent[1]['data']['changes'])->toBe(['fields' => ['description']])
        ->and(json_encode($sent))->not->toContain('payroll database');
});

it('publishes assets handed over and returned, without naming the holder', function (): void {
    $asset = Asset::factory()->create(['asset_tag' => 'LT-0042', 'name' => 'Finance laptop']);
    $asset->forceFill(['assigned_to_id' => $this->person->id, 'status' => 'deployed'])->save();
    $asset->forceFill(['notes' => 'New charger'])->save();
    $asset->forceFill(['assigned_to_id' => null, 'status' => 'in_stock'])->save();

    $sent = publishedWebhooks();
    expect(array_column($sent, 'event'))->toBe(['asset.assigned', 'asset.returned'])
        ->and($sent[0]['data'])->toEqual(['id' => $asset->id, 'reference' => 'LT-0042', 'title' => 'Finance laptop', 'url' => route('api.v1.assets.show', $asset->id)])
        ->and(json_encode($sent))->not->toContain($this->person->id);
});

it('publishes risks registered, their score changes and acceptance decisions', function (): void {
    $risk = Risk::factory()->create(['title' => 'Ransomware on payroll', 'inherent_likelihood' => 4, 'inherent_impact' => 5]);
    ScoreRecord::capture($risk, $this->person->id);
    ScoreRecord::capture($risk, $this->person->id);
    $acceptance = new Acceptance;
    $acceptance->forceFill(['organization_id' => $risk->organization_id, 'risk_id' => $risk->id, 'likelihood' => 2, 'impact' => 2, 'justification' => 'Limited exposure', 'status' => 'pending', 'requested_by' => $this->person->id, 'requested_at' => now()])->save();
    $acceptance->forceFill(['status' => 'approved', 'decided_by' => $this->person->id, 'decided_at' => now()])->save();

    $sent = publishedWebhooks();
    expect(array_column($sent, 'event'))->toBe(['risk.registered', 'risk.score_changed', 'risk.acceptance_decided'])
        ->and($sent[0]['data']['reference'])->toBe($risk->code())
        ->and($sent[1]['data']['changes'])->toEqual(['inherent_score' => 20, 'residual_score' => null])
        ->and($sent[2]['data']['changes'])->toBe(['decision' => 'approved']);
});

it('publishes findings raised and closed', function (): void {
    $finding = Finding::factory()->create(['title' => 'Backups not tested', 'description' => 'Details for the auditors only']);
    $finding->forceFill(['status' => 'verification'])->save();
    $finding->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

    $sent = publishedWebhooks();
    expect(array_column($sent, 'event'))->toBe(['finding.raised', 'finding.closed'])
        ->and($sent[1]['data'])->toMatchArray(['reference' => $finding->code(), 'title' => 'Backups not tested', 'url' => route('api.v1.compliance.findings.show', $finding->id)])
        ->and(json_encode($sent))->not->toContain('auditors only');
});

it('publishes continuity plans approved, activated, their activations closed and exercises completed', function (): void {
    $plan = Plan::factory()->create(['name' => 'Payroll DRP']);
    $version = PlanVersion::factory()->create(['plan_id' => $plan->id, 'status' => 'submitted']);
    $version->forceFill(['status' => 'approved', 'decided_at' => now()])->save();
    $activation = new Activation;
    $activation->forceFill(['organization_id' => $plan->organization_id, 'plan_id' => $plan->id, 'version_id' => $version->id, 'scenario' => 'it_outage', 'reason' => 'Database corrupted', 'activated_at' => now(), 'activated_by' => $this->person->id])->save();
    $activation->forceFill(['restored_at' => now(), 'closed_at' => now(), 'closed_by' => $this->person->id])->save();
    $exercise = Exercise::factory()->create(['plan_id' => $plan->id, 'version_id' => $version->id, 'status' => 'in_progress', 'started_at' => now()]);
    $exercise->forceFill(['status' => 'completed', 'outcome' => 'successful', 'completed_at' => now()])->save();

    $sent = publishedWebhooks();
    expect(array_column($sent, 'event'))->toBe(['continuity.plan_approved', 'continuity.plan_activated', 'continuity.activation_closed', 'continuity.exercise_completed'])
        ->and(array_unique(array_map(fn (array $event): string => $event['data']['url'], $sent)))->toBe([route('api.v1.continuity.plans.show', $plan->id)])
        ->and($sent[1]['data']['changes'])->toEqual(['record_id' => $activation->id, 'scenario' => 'it_outage'])
        ->and($sent[3]['data']['changes'])->toEqual(['record_id' => $exercise->id, 'outcome' => 'successful'])
        ->and(json_encode($sent))->not->toContain('Database corrupted');
});

it('publishes nothing to other organizations', function (): void {
    $other = Organization::factory()->create();
    app(OrganizationContext::class)->runAs($other, fn () => ConfigurationItem::factory()->create());

    expect(WebhookDelivery::query()->withoutGlobalScopes()->count())->toBe(0);
});
