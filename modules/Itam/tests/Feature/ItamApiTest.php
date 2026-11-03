<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;
use Modules\Itam\Tests\Support\ItamScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->itam = ItamScenario::create();
});

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function itamHeaders(User $user, array $abilities = ['assets:read', 'assets:write']): array
{
    $token = app(IssueApiToken::class)->handle($user, 'itam', $abilities);
    auth()->forgetGuards();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

it('registers and changes assets with the category\'s attributes, keeping the status to its endpoints', function (): void {
    $headers = itamHeaders($this->itam->manager);

    $id = $this->postJson('/api/v1/assets', ['category_id' => $this->itam->laptops->id, 'asset_tag' => 'LT-100', 'serial_number' => 'SN-1', 'purchase_cost' => '1200.00', 'currency' => 'eur', 'properties' => ['memory_gb' => 32]], $headers)
        ->assertCreated()->assertJsonPath('data.status', 'in_stock')->assertJsonPath('data.currency', 'EUR')->assertJsonPath('data.properties.memory_gb', 32)
        ->json('data.id');

    $this->postJson('/api/v1/assets', ['category_id' => $this->itam->laptops->id, 'asset_tag' => 'lt-100'], $headers)->assertUnprocessable()->assertJsonValidationErrors('asset_tag');
    $this->patchJson("/api/v1/assets/{$id}", ['status' => 'deployed'], $headers)->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->patchJson("/api/v1/assets/{$id}", ['name' => 'Ana\'s laptop'], $headers)->assertOk()->assertJsonPath('data.asset_tag', 'LT-100')->assertJsonPath('data.properties.memory_gb', 32);
    $this->getJson('/api/v1/assets?filter[category]=laptop&filter[search]=LT-1', $headers)->assertOk()->assertJsonCount(1, 'data');
});

it('hands assets over and back, keeping the assignment history', function (): void {
    $laptop = $this->itam->laptop(['status' => 'in_stock']);
    $headers = itamHeaders($this->itam->agent);

    $this->postJson("/api/v1/assets/{$laptop->id}/assign", ['user_id' => $this->itam->employee->id, 'notify' => false], $headers)->assertOk()
        ->assertJsonPath('data.status', 'deployed')->assertJsonPath('data.holder.id', $this->itam->employee->id);
    $this->postJson("/api/v1/assets/{$laptop->id}/assign", ['user_id' => $this->itam->employee->id], $headers)->assertUnprocessable()->assertJsonValidationErrors('status');

    $this->postJson("/api/v1/assets/{$laptop->id}/status", ['status' => 'in_stock', 'notes' => 'Returned'], $headers)->assertOk()->assertJsonPath('data.status', 'in_stock')->assertJsonPath('data.holder', null);
    expect(AssetAssignment::query()->where('asset_id', $laptop->id)->whereNotNull('returned_at')->count())->toBe(1);

    // Retiring is a register change: agents may hand out, not retire.
    $this->postJson("/api/v1/assets/{$laptop->id}/status", ['status' => 'retired'], $headers)->assertForbidden();
});

it('manages licences and seats without ever showing the key', function (): void {
    $product = SoftwareProduct::factory()->create(['name' => 'Office Suite']);
    $headers = itamHeaders($this->itam->manager);

    $id = $this->postJson('/api/v1/licenses', ['product_id' => $product->id, 'name' => 'Office 2026', 'model' => 'per_user', 'quantity' => 5, 'license_key' => 'SECRET-KEY-123'], $headers)
        ->assertCreated()->assertJsonPath('data.has_key', true)->assertJsonMissing(['license_key' => 'SECRET-KEY-123'])
        ->json('data.id');
    expect(json_encode($this->getJson("/api/v1/licenses/{$id}", $headers)->json()))->not->toContain('SECRET-KEY-123');

    $this->postJson("/api/v1/licenses/{$id}/seats", ['user_id' => $this->itam->employee->id], $headers)->assertOk()->assertJsonPath('data.seats_used', 1)->assertJsonPath('data.seats.0.user.id', $this->itam->employee->id);
    $this->postJson("/api/v1/licenses/{$id}/seats", ['asset_id' => $this->itam->laptop()->id], $headers)->assertUnprocessable()->assertJsonValidationErrors('asset_id');

    $seat = License::query()->findOrFail($id)->assignments()->sole();
    $this->deleteJson("/api/v1/licenses/{$id}/seats/{$seat->id}", [], $headers)->assertNoContent();
    $this->patchJson("/api/v1/licenses/{$id}", ['remove_key' => true], $headers)->assertOk()->assertJsonPath('data.has_key', false)->assertJsonPath('data.name', 'Office 2026');
});

it('applies abilities and permissions, and keeps to the organization', function (): void {
    $laptop = $this->itam->laptop();
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Asset::factory()->create(['asset_tag' => 'THEIR-1']));

    $this->patchJson("/api/v1/assets/{$laptop->id}", ['name' => 'x'], itamHeaders($this->itam->manager, ['assets:read']))->assertForbidden();
    $this->patchJson("/api/v1/assets/{$laptop->id}", ['name' => 'x'], itamHeaders($this->itam->agent))->assertForbidden();
    $this->getJson("/api/v1/assets/{$theirs->id}", itamHeaders($this->itam->manager))->assertNotFound();
    $this->getJson('/api/v1/assets', itamHeaders($this->itam->manager))->assertJsonMissing(['asset_tag' => 'THEIR-1']);
    $this->getJson('/api/v1/assets', itamHeaders($this->itam->employee))->assertForbidden();
});
