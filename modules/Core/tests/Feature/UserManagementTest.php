<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Notifications\PasswordLink;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\Users\Pages\ManageUsers;
use Modules\Core\Http\Livewire\Auth\ResetPassword;

beforeEach(function (): void {
    Notification::fake();
    Filament::setCurrentPanel('admin');
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $this->admin->assignRole(DefaultRole::Administrator->value);
    $this->actingAs($this->admin);
});

function roleId(DefaultRole $role): string
{
    return (string) Role::query()->where('name', $role->value)->whereNull('organization_id')->value('id');
}

it('is for administrators only', function (): void {
    $lead = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $lead->assignRole(DefaultRole::TeamLead->value);
    $lead->givePermissionTo('core.admin.access');

    $this->get('/admin/people')->assertOk();
    $this->actingAs($lead)->get('/admin/people')->assertForbidden();
});

it('invites a new person, who chooses their own password from the link', function (): void {
    Livewire::test(ManageUsers::class)
        ->callAction('invite', data: ['name' => 'Ana Pop', 'email' => 'Ana@Example.com', 'role_ids' => [roleId(DefaultRole::Agent)]])
        ->assertHasNoActionErrors();

    $ana = User::query()->where('email', 'ana@example.com')->sole();
    expect($ana->isMemberOf($ana->current_organization_id ?? ''))->toBeTrue()
        ->and($ana->hasRole(DefaultRole::Agent->value))->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::Invited)->where('auditable_id', $ana->id)->exists())->toBeTrue();

    $token = '';
    Notification::assertSentTo($ana, PasswordLink::class, function (PasswordLink $link) use (&$token): bool {
        $token = $link->token;

        return $link->invitation;
    });

    auth()->logout();
    $this->get(route('invitation.accept', ['token' => $token, 'email' => 'ana@example.com']))->assertOk()->assertSee(__('core::security.reset.welcome_intro'));
    Livewire::withQueryParams(['email' => 'ana@example.com'])->test(ResetPassword::class, ['token' => $token])
        ->set('password', 'ana chooses this one')->set('passwordConfirmation', 'ana chooses this one')
        ->call('save')
        ->assertHasNoErrors();
});

it('adds someone who already has an account, without inviting them again', function (): void {
    $existing = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => User::factory()->create(['email' => 'bob@example.com']));

    Livewire::test(ManageUsers::class)
        ->callAction('invite', data: ['name' => 'Bob', 'email' => 'bob@example.com', 'role_ids' => [roleId(DefaultRole::Requester)]]);

    expect($existing->fresh()->isMemberOf($this->organization->id))->toBeTrue();
    Notification::assertNotSentTo($existing, PasswordLink::class);
});

it('refuses roles of another organization', function (): void {
    $foreignRole = Role::query()->create(['name' => 'auditor', 'guard_name' => 'web', 'organization_id' => Organization::factory()->create()->id]);

    Livewire::test(ManageUsers::class)
        ->callAction('invite', data: ['name' => 'Eve', 'email' => 'eve@example.com', 'role_ids' => [(string) $foreignRole->id]])
        ->assertHasActionErrors(['role_ids.0']);

    expect(User::query()->where('email', 'eve@example.com')->exists())->toBeFalse();
});

it('changes roles of local accounts, never of AD accounts or of oneself', function (): void {
    $local = User::factory()->memberOf($this->organization)->create();
    $local->assignRole(DefaultRole::Requester->value);
    $directory = User::factory()->fromDirectory()->memberOf($this->organization)->create();
    $directory->assignRole(DefaultRole::Requester->value);

    foreach ([$local, $directory, $this->admin] as $person) {
        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $person, data: ['name' => $person->name, 'role_ids' => [roleId(DefaultRole::TeamLead)]]);
    }

    expect($local->fresh()->hasRole(DefaultRole::TeamLead->value))->toBeTrue()
        ->and($directory->fresh()->hasRole(DefaultRole::TeamLead->value))->toBeFalse()
        ->and($this->admin->fresh()->hasRole(DefaultRole::Administrator->value))->toBeTrue()
        ->and($this->admin->fresh()->hasRole(DefaultRole::TeamLead->value))->toBeFalse();
});

it('deactivates only people who belong to this organization alone', function (): void {
    $only = User::factory()->memberOf($this->organization)->create();
    $shared = User::factory()->memberOf($this->organization)->create();
    $shared->organizations()->attach(Organization::factory()->create());

    foreach ([$only, $shared] as $person) {
        Livewire::test(ManageUsers::class)->callTableAction('edit', $person, data: ['name' => $person->name, 'is_active' => false]);
    }

    expect($only->fresh()->is_active)->toBeFalse()
        ->and($shared->fresh()->is_active)->toBeTrue();
});

it('resets two-factor authentication of someone else, audited', function (): void {
    $agent = User::factory()->memberOf($this->organization)->withTwoFactor()->create();

    Livewire::test(ManageUsers::class)->callTableAction('reset_mfa', $agent);

    expect($agent->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and(AuditLog::query()->where('event', AuditEvent::MfaReset)->where('auditable_id', $agent->id)->where('user_id', $this->admin->id)->exists())->toBeTrue();

    Livewire::test(ManageUsers::class)->assertTableActionHidden('reset_mfa', $this->admin);
});

it('removes someone from the organization and lists only its people', function (): void {
    $leaving = User::factory()->memberOf($this->organization)->create();
    $leaving->assignRole(DefaultRole::Agent->value);
    $stranger = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => User::factory()->create());

    Livewire::test(ManageUsers::class)
        ->assertCanSeeTableRecords([$leaving])
        ->assertCanNotSeeTableRecords([$stranger])
        ->callTableAction('remove', $leaving);

    expect($leaving->fresh()->isMemberOf($this->organization->id))->toBeFalse()
        ->and($leaving->fresh()->roles()->count())->toBe(0);
});
