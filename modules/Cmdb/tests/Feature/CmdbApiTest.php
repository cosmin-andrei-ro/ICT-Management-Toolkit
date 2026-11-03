<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Cmdb\Tests\Support\CmdbScenario;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    $this->cmdb = CmdbScenario::create();
    $this->cmdb->type->forceFill(['attribute_schema' => [['key' => 'cpu_cores', 'label' => 'CPU cores', 'type' => 'number', 'required' => false]]])->save();
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function cmdbHeaders(User $user, array $abilities = ['cmdb:read', 'cmdb:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'cmdb', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('lists types and items with filters and sorts', function (): void {
    $this->cmdb->item('erp-db', ['criticality' => 'critical']);
    $this->cmdb->item('wiki', ['criticality' => 'low']);
    $headers = cmdbHeaders($this->cmdb->agent, ['cmdb:read']);

    $this->getJson('/api/v1/cmdb/types', $headers)->assertOk()->assertJsonPath('data.0.key', 'generic')->assertJsonPath('data.0.attributes.0.key', 'cpu_cores');
    $this->getJson('/api/v1/cmdb/items?filter[criticality]=critical', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'erp-db');
    $this->getJson('/api/v1/cmdb/items?sort=-name&filter[type]=generic', $headers)->assertJsonPath('data.0.name', 'wiki');
    $this->getJson('/api/v1/cmdb/items?filter[search]=ERP', $headers)->assertJsonCount(1, 'data');
});

it('creates, changes and deletes items with the type\'s attributes checked', function (): void {
    $headers = cmdbHeaders($this->cmdb->agent);

    $this->postJson('/api/v1/cmdb/items', ['type_id' => $this->cmdb->type->id, 'name' => 'srv-01', 'properties' => ['cpu_cores' => 'many']], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('properties.cpu_cores');

    $id = $this->postJson('/api/v1/cmdb/items', ['type_id' => $this->cmdb->type->id, 'name' => 'srv-01', 'identifier' => 'SRV-01', 'criticality' => 'high', 'owner_id' => $this->cmdb->lead->id, 'properties' => ['cpu_cores' => 16]], $headers)
        ->assertCreated()->assertJsonPath('data.criticality', 'high')->assertJsonPath('data.owner.id', $this->cmdb->lead->id)->assertJsonPath('data.properties.cpu_cores', 16)
        ->json('data.id');

    $this->postJson('/api/v1/cmdb/items', ['type_id' => $this->cmdb->type->id, 'name' => 'other', 'identifier' => 'srv-01'], $headers)->assertUnprocessable()->assertJsonValidationErrors('identifier');

    // PATCH: only what is sent changes.
    $this->patchJson("/api/v1/cmdb/items/{$id}", ['status' => 'maintenance'], $headers)->assertOk()
        ->assertJsonPath('data.status', 'maintenance')->assertJsonPath('data.name', 'srv-01')->assertJsonPath('data.criticality', 'high')->assertJsonPath('data.properties.cpu_cores', 16);

    $this->deleteJson("/api/v1/cmdb/items/{$id}", [], $headers)->assertNoContent();
    expect(ConfigurationItem::query()->find($id))->toBeNull();
});

it('relates items and shows the relationships both ways', function (): void {
    $app = $this->cmdb->item('erp');
    $db = $this->cmdb->item('erp-db');
    $headers = cmdbHeaders($this->cmdb->agent);

    $relationship = $this->postJson('/api/v1/cmdb/relationships', ['source_id' => $app->id, 'type' => 'depends_on', 'target_id' => $db->id], $headers)->assertCreated()->json('data.id');
    $this->postJson('/api/v1/cmdb/relationships', ['source_id' => $app->id, 'type' => 'depends_on', 'target_id' => $db->id], $headers)->assertOk();
    $this->postJson('/api/v1/cmdb/relationships', ['source_id' => $app->id, 'type' => 'depends_on', 'target_id' => $app->id], $headers)->assertUnprocessable();

    $this->getJson("/api/v1/cmdb/items/{$db->id}", $headers)->assertOk()->assertJsonPath('data.incoming.0.item.name', 'erp')->assertJsonPath('data.incoming.0.type', 'depends_on');
    $this->deleteJson("/api/v1/cmdb/relationships/{$relationship}", [], $headers)->assertNoContent();
    expect(Relationship::query()->count())->toBe(0);
});

it('applies abilities and permissions, and keeps to the organization', function (): void {
    $item = $this->cmdb->item('erp');
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), function () {
        $type = CiType::factory()->create();

        return ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'Their server']);
    });

    $readOnly = cmdbHeaders($this->cmdb->agent, ['cmdb:read']);
    $this->patchJson("/api/v1/cmdb/items/{$item->id}", ['name' => 'x'], $readOnly)->assertForbidden();
    $this->getJson("/api/v1/cmdb/items/{$theirs->id}", $readOnly)->assertNotFound();
    $this->getJson('/api/v1/cmdb/items', $readOnly)->assertJsonMissing(['name' => 'Their server']);
    $this->postJson('/api/v1/cmdb/relationships', ['source_id' => $item->id, 'type' => RelationshipType::DependsOn->value, 'target_id' => $theirs->id], cmdbHeaders($this->cmdb->agent))->assertUnprocessable();

    // Requesters have no CMDB permission: the ability alone does not open it.
    $this->getJson('/api/v1/cmdb/items', cmdbHeaders($this->cmdb->requester))->assertForbidden();
});
