<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Livewire\Auth\Login;

/*
 * Runs against the development Samba AD container (docs/development/active-directory.md):
 *   COMPOSE_PROFILES=ad ./vendor/bin/sail up -d
 *   ./vendor/bin/sail exec -e AD_INTEGRATION=1 laravel.test php vendor/bin/pest --group=active-directory
 * Skipped unless AD_INTEGRATION is set.
 */

const USER_PASSWORD = 'User-Dev-Passw0rd!';

beforeEach(function (): void {
    if (getenv('AD_INTEGRATION') === false) {
        $this->markTestSkipped('Set AD_INTEGRATION=1 and start the Samba AD container to run these tests.');
    }

    config()->set('core.directory', [
        'enabled' => true,
        'hosts' => [getenv('AD_INTEGRATION_HOST') ?: 'dc1.corp.example'],
        'port' => 636,
        'base_dn' => 'dc=corp,dc=example',
        'username' => 'svc-ictmt@corp.example',
        'password' => getenv('AD_INTEGRATION_SERVICE_PASSWORD') ?: 'Service-Dev-Passw0rd!',
        'encryption' => 'ldaps',
        'allow_insecure' => false,
        'ca_cert' => '/etc/ssl/samba-ad/ca.pem',
        'timeout' => 5,
    ]);
    app()->forgetInstance(Directory::class);

    $this->directory = app(Directory::class);
})->group('active-directory');

it('verifies passwords by binding as the user', function (): void {
    $jane = $this->directory->findUser('jdoe');

    expect($jane)->not->toBeNull()
        ->and($this->directory->authenticate($jane, USER_PASSWORD))->toBeTrue()
        ->and($this->directory->authenticate($jane, 'wrong-password'))->toBeFalse()
        ->and($this->directory->authenticate($jane, ''))->toBeFalse();
})->group('active-directory');

it('resolves nested group membership', function (): void {
    $bo = $this->directory->findUser('CORP\\bnested');

    expect($this->directory->groupsOf($bo))->toEqualCanonicalizing([
        'cn=it-l2,ou=groups,dc=corp,dc=example',
        'cn=it-servicedesk,ou=groups,dc=corp,dc=example',
    ]);
})->group('active-directory');

it('signs in through a nested group mapping end to end', function (): void {
    app(SyncPermissions::class)->handle();
    $organization = Organization::factory()->create();
    DirectoryGroupMapping::factory()->for($organization)->forRole(DefaultRole::Agent)
        ->create(['group_dn' => 'CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example']);

    Livewire::test(Login::class)
        ->set('login', 'bo.nested@corp.example')
        ->set('password', USER_PASSWORD)
        ->call('login')
        ->assertHasNoErrors();

    $user = User::query()->where('directory_username', 'bnested')->sole();
    $this->assertAuthenticatedAs($user);

    $roles = app(OrganizationContext::class)->runAs($organization, fn () => User::query()->findOrFail($user->id)->getRoleNames()->all());
    expect($roles)->toBe(['agent']);
})->group('active-directory');

it('rejects disabled accounts and users without a mapped group', function (string $login, AuditEvent $event): void {
    app(SyncPermissions::class)->handle();
    DirectoryGroupMapping::factory()->for(Organization::factory()->create())->forRole(DefaultRole::Agent)
        ->create(['group_dn' => 'CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example']);

    Livewire::test(Login::class)
        ->set('login', $login)
        ->set('password', USER_PASSWORD)
        ->call('login')
        ->assertHasErrors(['login']);

    $this->assertGuest();
    expect(AuditLog::query()->where('event', $event)->exists())->toBeTrue();
})->with([
    // AD itself refuses the bind of a disabled account.
    'disabled in AD' => ['cdisabled', AuditEvent::LoginFailed],
    'no mapped group' => ['dnogroup', AuditEvent::LoginDenied],
])->group('active-directory');
