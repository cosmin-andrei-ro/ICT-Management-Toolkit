<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Permission;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Exceptions\MissingOrganizationContext;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();

    $this->context = app(OrganizationContext::class);
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
});

/**
 * Roles are cached on the model per team; a fresh instance reflects the current context.
 */
function userInContext(User $user): User
{
    return User::query()->findOrFail($user->id);
}

describe('permission sync', function (): void {
    it('creates every registered permission and the default roles', function (): void {
        $registered = app(PermissionRegistry::class)->permissions();

        expect(Permission::query()->pluck('name')->sort()->values()->all())->toBe($registered)
            ->and(Role::query()->whereNull('organization_id')->pluck('name')->sort()->values()->all())
            ->toBe(collect(DefaultRole::cases())->map->value->sort()->values()->all());
    });

    it('gives the administrator role every permission', function (): void {
        $administrator = Role::query()->where('name', DefaultRole::Administrator->value)->sole();

        expect($administrator->permissions()->count())->toBe(count(app(PermissionRegistry::class)->permissions()));
    });

    it('is idempotent and resets default roles to their code-defined permissions', function (): void {
        $agent = Role::query()->where('name', DefaultRole::Agent->value)->sole();
        $agent->givePermissionTo('core.role.manage');

        app(SyncPermissions::class)->handle();

        expect(Permission::query()->count())->toBe(count(app(PermissionRegistry::class)->permissions()))
            ->and($agent->fresh()?->hasPermissionTo('core.role.manage'))->toBeFalse();
    });

    it('writes nothing to the audit log when already in sync', function (): void {
        $before = AuditLog::query()->count();

        app(SyncPermissions::class)->handle();

        expect(AuditLog::query()->count())->toBe($before);
    });

    it('reports permissions no longer defined in code without deleting them', function (): void {
        Permission::query()->create(['name' => 'legacy.thing.do', 'guard_name' => 'web']);

        expect(app(SyncPermissions::class)->handle())->toBe(['legacy.thing.do'])
            ->and(Permission::query()->where('name', 'legacy.thing.do')->exists())->toBeTrue();
    });

    it('rejects duplicate global roles at the database level', function (): void {
        Role::query()->create(['organization_id' => null, 'name' => DefaultRole::Agent->value, 'guard_name' => 'web']);
    })->throws(QueryException::class, 'core_roles_name_unique');
});

describe('permission registry', function (): void {
    it('rejects names that do not follow module.entity.action', function (): void {
        (new PermissionRegistry)->register(['ticketing.create']);
    })->throws(InvalidArgumentException::class);

    it('rejects granting an unregistered permission to a default role', function (): void {
        $registry = new PermissionRegistry;
        $registry->grantToDefaultRole(DefaultRole::Agent, ['core.ghost.view']);

        $registry->permissionsFor(DefaultRole::Agent);
    })->throws(InvalidArgumentException::class);
});

describe('organization-scoped roles', function (): void {
    it('grants a role only within the organization it was assigned in', function (): void {
        $user = User::factory()->memberOf($this->orgA)->create();
        $user->organizations()->attach($this->orgB);

        $this->context->runAs($this->orgA, fn () => $user->assignRole(DefaultRole::Administrator->value));

        $this->context->set($this->orgA);
        expect(userInContext($user)->can('core.department.create'))->toBeTrue();

        $this->context->set($this->orgB);
        expect(userInContext($user)->can('core.department.create'))->toBeFalse();
    });

    it('cannot assign a role defined by another organization', function (): void {
        $this->context->runAs($this->orgB, fn () => Role::query()->create(['name' => 'external-reviewer', 'guard_name' => 'web']));
        $user = User::factory()->memberOf($this->orgA)->create();

        $this->context->runAs($this->orgA, fn () => $user->assignRole('external-reviewer'));
    })->throws(RoleDoesNotExist::class);

    it('creates roles in the current organization unless a global role is explicitly requested', function (): void {
        $orgRole = $this->context->runAs($this->orgA, fn () => Role::query()->create(['name' => 'external-reviewer', 'guard_name' => 'web']));
        $globalRole = Role::query()->create(['organization_id' => null, 'name' => 'observer', 'guard_name' => 'web']);

        expect($orgRole->organization_id)->toBe($this->orgA->id)
            ->and($globalRole->isGlobal())->toBeTrue();
    });

    it('refuses to create a role without an organization context or an explicit null', function (): void {
        Role::query()->create(['name' => 'external-reviewer', 'guard_name' => 'web']);
    })->throws(MissingOrganizationContext::class);

    it('denies everything to a member without roles', function (): void {
        $user = User::factory()->memberOf($this->orgA)->create();
        $this->context->set($this->orgA);

        foreach (app(PermissionRegistry::class)->permissions() as $permission) {
            expect($user->can($permission))->toBeFalse();
        }
    });
});

describe('department policy', function (): void {
    beforeEach(function (): void {
        $this->agent = User::factory()->memberOf($this->orgA)->create();
        $this->context->runAs($this->orgA, fn () => $this->agent->assignRole(DefaultRole::Agent->value));
        $this->context->set($this->orgA);
        $this->agent = userInContext($this->agent);
    });

    it('lets an agent view but not change departments', function (): void {
        $department = Department::factory()->create();

        expect(Gate::forUser($this->agent)->allows('viewAny', Department::class))->toBeTrue()
            ->and(Gate::forUser($this->agent)->allows('view', $department))->toBeTrue()
            ->and(Gate::forUser($this->agent)->allows('create', Department::class))->toBeFalse()
            ->and(Gate::forUser($this->agent)->allows('update', $department))->toBeFalse()
            ->and(Gate::forUser($this->agent)->allows('delete', $department))->toBeFalse();
    });

    it('denies access to a department of another organization even with the permission', function (): void {
        $foreign = $this->context->runAs($this->orgB, fn () => Department::factory()->create());
        $loadedWithoutScope = Department::query()->withoutGlobalScope(OrganizationScope::class)->findOrFail($foreign->id);

        expect(Gate::forUser($this->agent)->allows('view', $loadedWithoutScope))->toBeFalse();
    });
});

describe('audit of authorization changes', function (): void {
    it('records role assignment and revocation on the user', function (): void {
        $user = User::factory()->memberOf($this->orgA)->create();
        $this->context->set($this->orgA);

        $user->assignRole(DefaultRole::Agent->value);
        $user->removeRole(DefaultRole::Agent->value);

        $entries = AuditLog::query()
            ->where('auditable_type', 'core.user')
            ->where('auditable_id', $user->id)
            ->whereIn('event', [AuditEvent::RoleAssigned, AuditEvent::RoleRevoked])
            ->orderBy('id')
            ->get();

        expect($entries)->toHaveCount(2)
            ->and($entries[0]->event)->toBe(AuditEvent::RoleAssigned)
            ->and($entries[0]->new_values)->toBe(['roles' => ['agent']])
            ->and($entries[0]->organization_id)->toBe($this->orgA->id)
            ->and($entries[1]->event)->toBe(AuditEvent::RoleRevoked)
            ->and($entries[1]->old_values)->toBe(['roles' => ['agent']]);
    });
});
