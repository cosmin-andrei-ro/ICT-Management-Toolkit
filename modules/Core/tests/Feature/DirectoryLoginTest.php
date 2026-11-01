<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Directory\DirectoryUser;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Livewire\Auth\Login;
use Modules\Core\Tests\Support\FakeDirectory;

const SERVICE_DESK_GROUP = 'CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example';
const ADMINS_GROUP = 'CN=IT-Admins,OU=Groups,DC=corp,DC=example';

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();

    $this->directory = new FakeDirectory;
    app()->instance(Directory::class, $this->directory);

    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();

    $this->jane = new DirectoryUser(
        guid: '0193a1b2-0000-7000-8000-00000000000a',
        distinguishedName: 'CN=Jane Doe,OU=Staff,DC=corp,DC=example',
        username: 'jdoe',
        name: 'Jane Doe',
        email: 'jane.doe@corp.example',
        enabled: true,
    );

    DirectoryGroupMapping::factory()->for($this->orgA)->forRole(DefaultRole::Agent)->create(['group_dn' => SERVICE_DESK_GROUP]);
});

function directoryLogin(string $login, string $password, bool $remember = false): Testable
{
    return Livewire::test(Login::class)
        ->set('login', $login)
        ->set('password', $password)
        ->set('remember', $remember)
        ->call('login');
}

/**
 * @return list<string>
 */
function roleNamesIn(User $user, Organization $organization): array
{
    return app(OrganizationContext::class)->runAs($organization, function () use ($user): array {
        /** @var list<string> */
        return User::query()->findOrFail($user->id)->getRoleNames()->sort()->values()->all();
    });
}

function janeAccount(): User
{
    return User::query()->where('directory_guid', '0193a1b2-0000-7000-8000-00000000000a')->firstOrFail();
}

describe('first sign-in', function (): void {
    it('creates the account from AD and grants roles from mapped groups', function (string $identifier): void {
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP]);

        directoryLogin($identifier, 'ad-secret')->assertHasNoErrors()->assertRedirect(route('home'));

        $user = janeAccount();
        $this->assertAuthenticatedAs($user);

        expect($user->auth_source)->toBe(AuthSource::Ldap)
            ->and($user->password)->toBeNull()
            ->and($user->name)->toBe('Jane Doe')
            ->and($user->email)->toBe('jane.doe@corp.example')
            ->and($user->current_organization_id)->toBe($this->orgA->id)
            ->and($user->isMemberOf($this->orgB))->toBeFalse()
            ->and(roleNamesIn($user, $this->orgA))->toBe(['agent']);
    })->with(['username' => 'jdoe', 'domain prefix' => 'CORP\\jdoe', 'email' => 'Jane.Doe@corp.example']);

    it('maps roles per organization', function (): void {
        DirectoryGroupMapping::factory()->for($this->orgB)->forRole(DefaultRole::Administrator)->create(['group_dn' => ADMINS_GROUP]);
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP, ADMINS_GROUP]);

        directoryLogin('jdoe', 'ad-secret')->assertHasNoErrors();

        $user = janeAccount();
        expect(roleNamesIn($user, $this->orgA))->toBe(['agent'])
            ->and(roleNamesIn($user, $this->orgB))->toBe(['administrator']);
    });

    it('denies access when no group of the user is mapped', function (): void {
        $this->directory->addUser($this->jane, 'ad-secret', ['CN=Marketing,OU=Groups,DC=corp,DC=example']);

        directoryLogin('jdoe', 'ad-secret')->assertHasErrors(['login']);

        $this->assertGuest();
        expect(User::query()->where('directory_guid', $this->jane->guid)->exists())->toBeFalse();

        $entry = AuditLog::query()->where('event', AuditEvent::LoginDenied)->sole();
        expect($entry->new_values)->toBe(['email' => 'jdoe', 'reason' => 'no_mapped_group']);
    });

    it('never takes over a local account with the same email', function (): void {
        $local = User::factory()->memberOf($this->orgA)->create(['email' => 'jane.doe@corp.example']);
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP]);

        directoryLogin('jdoe', 'ad-secret')->assertHasErrors(['login']);

        $this->assertGuest();
        expect($local->fresh()?->auth_source)->toBe(AuthSource::Local)
            ->and(AuditLog::query()->where('event', AuditEvent::LoginDenied)->sole()->new_values['reason'] ?? null)->toBe('email_conflict');
    });
});

describe('credentials', function (): void {
    beforeEach(function (): void {
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP]);
    });

    it('rejects a wrong AD password with the generic message', function (): void {
        $errors = directoryLogin('jdoe', 'wrong')->errors()->get('login');

        expect($errors)->toBe([__('auth.failed')]);
        $this->assertGuest();
        expect(AuditLog::query()->where('event', AuditEvent::LoginFailed)->exists())->toBeTrue();
    });

    it('never accepts an empty password, even if validation were bypassed', function (): void {
        expect($this->directory->authenticate($this->jane, ''))->toBeFalse();

        directoryLogin('jdoe', '')->assertHasErrors(['password' => 'required']);
        $this->assertGuest();
    });

    it('rejects unknown users with the same message as wrong passwords', function (): void {
        expect(directoryLogin('nobody', 'ad-secret')->errors()->get('login'))->toBe([__('auth.failed')]);
    });

    it('has no local password fallback for directory accounts', function (): void {
        directoryLogin('jdoe', 'ad-secret');
        auth()->logout();

        $this->directory = (new FakeDirectory)->addUser($this->jane, 'changed-in-ad', [SERVICE_DESK_GROUP]);
        app()->instance(Directory::class, $this->directory);

        directoryLogin('jane.doe@corp.example', 'ad-secret')->assertHasErrors(['login']);
        $this->assertGuest();
    });

    it('never remembers directory accounts', function (): void {
        directoryLogin('jdoe', 'ad-secret', remember: true)->assertHasNoErrors();

        expect(janeAccount()->remember_token)->toBeNull();
    });

    it('reports an unreachable directory without counting a failed attempt', function (): void {
        $this->directory->makeUnavailable();

        expect(directoryLogin('jdoe', 'ad-secret')->errors()->get('login'))->toBe([__('core::auth.directory_unavailable')]);
        expect(AuditLog::query()->where('event', AuditEvent::LoginFailed)->exists())->toBeFalse();
    });
});

describe('later sign-ins', function (): void {
    it('follows group changes: new roles, removed organizations', function (): void {
        DirectoryGroupMapping::factory()->for($this->orgB)->forRole(DefaultRole::TeamLead)->create(['group_dn' => ADMINS_GROUP]);
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP, ADMINS_GROUP]);
        directoryLogin('jdoe', 'ad-secret');
        auth()->logout();

        // Moved out of the service desk group in AD.
        $this->directory->addUser($this->jane, 'ad-secret', [ADMINS_GROUP]);
        directoryLogin('jdoe', 'ad-secret')->assertHasNoErrors();

        $user = janeAccount();
        expect($user->isMemberOf($this->orgA))->toBeFalse()
            ->and(roleNamesIn($user, $this->orgA))->toBe([])
            ->and(roleNamesIn($user, $this->orgB))->toBe(['team-lead'])
            ->and($user->current_organization_id)->toBe($this->orgB->id);
    });

    it('overrides roles given manually to a directory account', function (): void {
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP]);
        directoryLogin('jdoe', 'ad-secret');
        auth()->logout();
        app(OrganizationContext::class)->runAs($this->orgA, fn () => janeAccount()->assignRole(DefaultRole::Administrator->value));

        directoryLogin('jdoe', 'ad-secret');

        expect(roleNamesIn(janeAccount(), $this->orgA))->toBe(['agent']);
    });

    it('logs nothing about roles when nothing changed', function (): void {
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP]);
        directoryLogin('jdoe', 'ad-secret');
        auth()->logout();
        $before = AuditLog::query()->whereIn('event', [AuditEvent::RoleAssigned, AuditEvent::RoleRevoked, AuditEvent::Updated])->count();

        directoryLogin('jdoe', 'ad-secret');

        expect(AuditLog::query()->whereIn('event', [AuditEvent::RoleAssigned, AuditEvent::RoleRevoked, AuditEvent::Updated])->count())->toBe($before);
    });

    it('denies accounts disabled in AD or deactivated locally', function (bool $disabledInAd): void {
        $this->directory->addUser($this->jane, 'ad-secret', [SERVICE_DESK_GROUP]);
        directoryLogin('jdoe', 'ad-secret');
        auth()->logout();

        if ($disabledInAd) {
            $disabled = new DirectoryUser($this->jane->guid, $this->jane->distinguishedName, 'jdoe', 'Jane Doe', 'jane.doe@corp.example', enabled: false);
            $this->directory->addUser($disabled, 'ad-secret', [SERVICE_DESK_GROUP]);
        } else {
            janeAccount()->forceFill(['is_active' => false])->save();
        }

        directoryLogin('jdoe', 'ad-secret')->assertHasErrors(['login']);
        $this->assertGuest();
    })->with(['disabled in AD' => true, 'deactivated locally' => false]);
});

describe('local accounts with AD enabled', function (): void {
    it('still sign in with their local password and are never looked up in AD', function (): void {
        $local = User::factory()->memberOf($this->orgA)->create(['email' => 'admin@example.com', 'password' => 'local-password-123']);
        $this->directory->makeUnavailable();

        directoryLogin('admin@example.com', 'local-password-123')->assertHasNoErrors();

        $this->assertAuthenticatedAs($local);
    });
});

describe('misconfiguration', function (): void {
    it('keeps local sign-in working when AD is enabled but misconfigured', function (): void {
        app()->forgetInstance(Directory::class);
        config()->set('core.directory', ['enabled' => true, 'hosts' => [], 'base_dn' => '']);
        $local = User::factory()->memberOf($this->orgA)->create(['email' => 'admin@example.com', 'password' => 'local-password-123']);

        $this->get(route('login'))->assertOk();
        directoryLogin('jdoe', 'whatever')->assertHasErrors(['login' => __('core::auth.directory_unavailable')]);
        directoryLogin('admin@example.com', 'local-password-123')->assertHasNoErrors();

        $this->assertAuthenticatedAs($local);
    });
});

describe('group mappings', function (): void {
    it('cannot use a role of another organization', function (): void {
        $foreignRole = app(OrganizationContext::class)->runAs($this->orgB, fn () => Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']));

        DirectoryGroupMapping::factory()->for($this->orgA)->create(['role_id' => $foreignRole->id]);
    })->throws(LogicException::class);

    it('stores group DNs lowercased', function (): void {
        $mapping = DirectoryGroupMapping::factory()->for($this->orgA)->create(['group_dn' => '  CN=Ops,DC=Corp,DC=Example ']);

        expect($mapping->group_dn)->toBe('cn=ops,dc=corp,dc=example');
    });
});
