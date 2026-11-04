<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

it('uses time-ordered UUID primary keys', function (): void {
    $user = User::factory()->create();

    expect($user->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('stores emails lowercased', function (): void {
    $user = User::factory()->create(['email' => '  Jane.Doe@Example.COM ']);

    expect($user->email)->toBe('jane.doe@example.com');
});

it('rejects mixed-case emails written around the model', function (): void {
    DB::table('users')->insert([
        'id' => (string) str()->uuid7(),
        'name' => 'Raw',
        'email' => 'Raw@Example.com',
        'password' => 'x',
    ]);
})->throws(QueryException::class, 'users_email_lowercase');

it('never serializes the password or remember token', function (): void {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('knows which organizations it belongs to', function (): void {
    $member = Organization::factory()->create();
    $other = Organization::factory()->create();
    $user = User::factory()->memberOf($member)->create();

    expect($user->isMemberOf($member))->toBeTrue()
        ->and($user->isMemberOf($other->id))->toBeFalse();
});

it('records the authenticated user as author', function (): void {
    $organization = Organization::factory()->create();
    $author = User::factory()->memberOf($organization)->create();
    $editor = User::factory()->memberOf($organization)->create();
    app(OrganizationContext::class)->set($organization);

    $this->actingAs($author);
    $department = Department::query()->create(['name' => 'IT Support']);

    $this->actingAs($editor);
    $department->update(['code' => 'IT']);

    expect($department->created_by)->toBe($author->id)
        ->and($department->updated_by)->toBe($editor->id);
});
