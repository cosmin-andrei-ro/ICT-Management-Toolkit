<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

function runSetup(object $test, string $password = 'correct horse battery', ?string $repeat = null): mixed
{
    return $test->artisan('core:setup')
        ->expectsQuestion('Organization name', 'Sample Industries')
        ->expectsQuestion('Administrator full name', 'Ana Admin')
        ->expectsQuestion('Administrator email (used to sign in)', 'Ana@Sample.example')
        ->expectsQuestion('Password (at least 12 characters)', $password)
        ->expectsQuestion('Repeat the password', $repeat ?? $password);
}

it('creates the first organization and an administrator who can sign in', function (): void {
    runSetup($this)->assertSuccessful();

    $user = User::query()->where('email', 'ana@sample.example')->sole();
    $organization = Organization::query()->sole();

    expect(Hash::check('correct horse battery', $user->password))->toBeTrue()
        ->and($user->isMemberOf($organization->id))->toBeTrue()
        ->and(app(OrganizationContext::class)->runAs($organization, fn (): bool => $user->hasRole('administrator')))->toBeTrue()
        ->and($organization->slug)->toBe('sample-industries');
});

it('refuses short passwords and mismatches, creating nothing', function (): void {
    runSetup($this, 'too short')->assertFailed();
    runSetup($this, 'correct horse battery', 'something else')->assertFailed();

    expect(User::query()->count())->toBe(0)
        ->and(Organization::query()->count())->toBe(0);
});

it('cannot be used again once an administrator exists', function (): void {
    runSetup($this)->assertSuccessful();

    $this->artisan('core:setup')->assertFailed();

    expect(User::query()->count())->toBe(1);
});
