<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\DirectoryGroupMappings\Pages\ManageDirectoryGroupMappings;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    Filament::setCurrentPanel('admin');

    $this->context = app(OrganizationContext::class);
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();

    $this->admin = User::factory()->memberOf($this->orgA)->withTwoFactor()->create();
    $this->context->runAs($this->orgA, fn () => $this->admin->assignRole(DefaultRole::Administrator->value));
});

function agentRoleId(): string
{
    return Role::query()->whereNull('organization_id')->where('name', DefaultRole::Agent->value)->firstOrFail()->id;
}

it('lists only the current organization\'s mappings', function (): void {
    $own = DirectoryGroupMapping::factory()->for($this->orgA)->create();
    $foreign = DirectoryGroupMapping::factory()->for($this->orgB)->create();

    $this->actingAs($this->admin);
    $this->context->set($this->orgA);

    Livewire::test(ManageDirectoryGroupMappings::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('creates a mapping in the current organization, lowercased and audited', function (): void {
    $this->actingAs($this->admin);
    $this->context->set($this->orgA);

    Livewire::test(ManageDirectoryGroupMappings::class)
        ->callAction('create', data: [
            'group_dn' => 'CN=IT-ServiceDesk,OU=Groups,DC=corp,DC=example',
            'role_id' => agentRoleId(),
        ])
        ->assertHasNoFormErrors();

    $mapping = DirectoryGroupMapping::query()->sole();

    expect($mapping->organization_id)->toBe($this->orgA->id)
        ->and($mapping->group_dn)->toBe('cn=it-servicedesk,ou=groups,dc=corp,dc=example')
        ->and($mapping->created_by)->toBe($this->admin->id)
        ->and(AuditLog::query()->where('auditable_type', 'core.directory_group_mapping')->where('event', AuditEvent::Created)->exists())->toBeTrue();
});

it('rejects invalid DNs, duplicates and roles of other organizations', function (): void {
    $this->actingAs($this->admin);
    $this->context->set($this->orgA);
    DirectoryGroupMapping::factory()->for($this->orgA)->create(['group_dn' => 'cn=ops,dc=corp,dc=example', 'role_id' => agentRoleId()]);
    $foreignRole = $this->context->runAs($this->orgB, fn () => Role::query()->create(['name' => 'auditor', 'guard_name' => 'web']));

    $attempts = [
        'invalid DN' => [['group_dn' => 'not a dn', 'role_id' => agentRoleId()], 'group_dn'],
        'duplicate, differently cased' => [['group_dn' => 'CN=Ops,DC=corp,DC=example', 'role_id' => agentRoleId()], 'group_dn'],
        'role of another organization' => [['group_dn' => 'cn=audit,dc=corp,dc=example', 'role_id' => $foreignRole->id], 'role_id'],
    ];

    foreach ($attempts as [$data, $field]) {
        Livewire::test(ManageDirectoryGroupMappings::class)
            ->callAction('create', data: $data)
            ->assertHasFormErrors([$field]);
    }

    expect(DirectoryGroupMapping::query()->count())->toBe(1);
});

it('is forbidden without core.directory.manage', function (): void {
    $agent = User::factory()->memberOf($this->orgA)->withTwoFactor()->create();
    $this->context->runAs($this->orgA, function () use ($agent): void {
        $agent->assignRole(DefaultRole::Agent->value);
        // Admin panel access alone must not be enough to manage mappings.
        $agent->givePermissionTo('core.admin.access');
    });

    $this->actingAs($agent)->get('/admin/directory-group-mappings')->assertForbidden();
});

it('is reachable by administrators', function (): void {
    $this->actingAs($this->admin)->get('/admin/directory-group-mappings')->assertOk();
});
