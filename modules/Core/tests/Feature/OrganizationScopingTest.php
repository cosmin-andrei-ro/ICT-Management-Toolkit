<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Modules\Core\Domain\Exceptions\CrossOrganizationAccess;
use Modules\Core\Domain\Exceptions\MissingOrganizationContext;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

beforeEach(function (): void {
    $this->context = app(OrganizationContext::class);
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();

    Department::factory()->count(2)->for($this->orgA)->create();
    Department::factory()->for($this->orgB)->create();
});

it('returns no rows when no organization is set', function (): void {
    expect(Department::query()->count())->toBe(0)
        ->and(Department::query()->withoutGlobalScope(OrganizationScope::class)->count())->toBe(3);
});

it('returns only rows of the current organization', function (): void {
    $this->context->set($this->orgA);

    expect(Department::query()->pluck('organization_id')->unique()->all())->toBe([$this->orgA->id]);

    $this->context->set($this->orgB);

    expect(Department::query()->count())->toBe(1);
});

it('cannot find a record of another organization by its id', function (): void {
    $foreign = Department::query()->withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $this->orgB->id)->firstOrFail();

    $this->context->set($this->orgA);

    expect(Department::query()->find($foreign->id))->toBeNull();
});

it('fills the organization from the context on create', function (): void {
    $this->context->set($this->orgB);

    $department = Department::query()->create(['name' => 'Infrastructure']);

    expect($department->organization_id)->toBe($this->orgB->id);
});

it('refuses to create a record without a context or an explicit organization', function (): void {
    Department::query()->create(['name' => 'Orphan']);
})->throws(MissingOrganizationContext::class);

it('refuses to create a record for another organization', function (): void {
    $this->context->set($this->orgA);

    Department::factory()->for($this->orgB)->create();
})->throws(CrossOrganizationAccess::class);

it('refuses to move a record to another organization', function (): void {
    $this->context->set($this->orgA);
    $department = Department::query()->firstOrFail();

    $department->forceFill(['organization_id' => $this->orgB->id])->save();
})->throws(CrossOrganizationAccess::class);

it('does not allow organization_id to be mass assigned', function (): void {
    $this->context->set($this->orgA);

    $department = Department::query()->create(['name' => 'Sneaky', 'organization_id' => $this->orgB->id]);

    expect($department->organization_id)->toBe($this->orgA->id);
});

it('restores the previous context after runAs', function (): void {
    $this->context->set($this->orgA);

    $count = $this->context->runAs($this->orgB, fn (): int => Department::query()->count());

    expect($count)->toBe(1)
        ->and($this->context->id())->toBe($this->orgA->id);
});

it('rejects a parent department from another organization at the database level', function (): void {
    $foreignParent = Department::query()->withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $this->orgB->id)->firstOrFail();

    $this->context->set($this->orgA);

    Department::query()->create(['name' => 'Child', 'parent_id' => $foreignParent->id]);
})->throws(QueryException::class, 'core_departments_parent_fk');

it('enforces unique department names per organization, case-insensitively', function (): void {
    $this->context->set($this->orgA);
    Department::query()->create(['name' => 'Networking']);

    Department::query()->create(['name' => 'NETWORKING']);
})->throws(QueryException::class, 'core_departments_org_name_unique');

it('allows the same department name in different organizations', function (): void {
    $this->context->runAs($this->orgA, fn () => Department::query()->create(['name' => 'Security']));
    $this->context->runAs($this->orgB, fn () => Department::query()->create(['name' => 'Security']));

    expect(Department::query()->withoutGlobalScope(OrganizationScope::class)->where('name', 'Security')->count())->toBe(2);
});
