<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Route::middleware(['web', 'auth', 'organization'])
        ->get('/__test/current-organization', fn () => ['organization' => app(OrganizationContext::class)->id()]);
});

it('sets the user\'s current organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->memberOf($organization)->create();

    $this->actingAs($user)
        ->getJson('/__test/current-organization')
        ->assertOk()
        ->assertExactJson(['organization' => $organization->id]);
});

it('falls back to another membership when the stored organization is no longer valid', function (): void {
    $kept = Organization::factory()->create();
    $removed = Organization::factory()->create();
    $user = User::factory()->memberOf($removed)->create();
    $user->organizations()->attach($kept);
    $user->organizations()->detach($removed);

    $this->actingAs($user)
        ->getJson('/__test/current-organization')
        ->assertExactJson(['organization' => $kept->id]);

    expect($user->fresh()?->current_organization_id)->toBe($kept->id);
});

it('ignores inactive organizations', function (): void {
    $inactive = Organization::factory()->create(['is_active' => false]);
    $user = User::factory()->memberOf($inactive)->create();

    $this->actingAs($user)
        ->getJson('/__test/current-organization')
        ->assertForbidden();
});

it('forbids users without any organization', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/__test/current-organization')
        ->assertForbidden()
        ->assertJsonPath('message', __('core::errors.no_organization'));
});

it('requires authentication', function (): void {
    $this->getJson('/__test/current-organization')->assertUnauthorized();
});
