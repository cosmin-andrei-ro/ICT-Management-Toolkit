<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Notifications\OrganizationInvitationReceived;
use Modules\Core\Application\Notifications\PasswordLink;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\OrganizationInvitation;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Sso\SsoIdentity;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\OrganizationInvitations\Pages\ListOrganizationInvitations;
use Modules\Core\Http\Filament\Resources\Users\Pages\ManageUsers;
use Modules\Core\Http\Livewire\Account\AccountSecurity;
use Modules\Core\Http\Livewire\Account\Invitations;
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
    Livewire::withQueryParams(['email' => 'ana@example.com'])->test(ResetPassword::class, ['token' => $token, 'invitation' => true])
        ->set('password', 'ana chooses this one')->set('passwordConfirmation', 'ana chooses this one')
        ->call('save')
        ->assertHasNoErrors();
});

it('asks someone who already has an account, who joins only by accepting', function (): void {
    $other = Organization::factory()->create();
    $existing = app(OrganizationContext::class)->runAs($other, fn () => User::factory()->create(['email' => 'bob@example.com']));

    Livewire::test(ManageUsers::class)
        ->callAction('invite', data: ['name' => 'Bob', 'email' => 'bob@example.com', 'role_ids' => [roleId(DefaultRole::Agent)]])
        // The same answer as for a new person: inviting does not reveal who has an account.
        ->assertNotified(__('core::users.invitation_sent'));

    expect($existing->fresh()->isMemberOf($this->organization->id))->toBeFalse();
    Notification::assertNotSentTo($existing, PasswordLink::class);
    Notification::assertSentTo($existing, OrganizationInvitationReceived::class, fn (OrganizationInvitationReceived $mail): bool => $mail->organization === $this->organization->name);

    // Nobody else can answer for Bob.
    $invitation = OrganizationInvitation::query()->sole();
    $this->actingAs(User::factory()->memberOf($this->organization)->create());
    Livewire::test(Invitations::class)->assertSee(__('core::invitations.none'))->call('accept', $invitation->id)->assertNotFound();

    app(OrganizationContext::class)->set($other);
    $this->actingAs($existing);
    Livewire::test(Invitations::class)->assertSee($this->organization->name)->call('accept', $invitation->id)->assertSee('You joined');

    expect($existing->fresh()->isMemberOf($this->organization->id))->toBeTrue()
        ->and(app(OrganizationContext::class)->runAs($this->organization, fn () => $existing->fresh()?->hasRole(DefaultRole::Agent->value)))->toBeTrue()
        ->and(OrganizationInvitation::query()->count())->toBe(0);
});

it('leaves the account as it was when the invitation is declined or expired', function (): void {
    $other = Organization::factory()->create();
    $existing = app(OrganizationContext::class)->runAs($other, fn () => User::factory()->create(['email' => 'bob@example.com']));
    Livewire::test(ManageUsers::class)->callAction('invite', data: ['name' => 'Bob', 'email' => 'bob@example.com', 'role_ids' => [roleId(DefaultRole::Agent)]]);
    $invitation = OrganizationInvitation::query()->sole();

    app(OrganizationContext::class)->set($other);
    $this->actingAs($existing);
    $this->travel(OrganizationInvitation::DAYS_VALID + 1)->days();
    Livewire::test(Invitations::class)->assertSee(__('core::invitations.none'))->call('accept', $invitation->id)->assertNotFound();
    $this->travelBack();
    Livewire::test(Invitations::class)->call('decline', $invitation->id);

    expect($existing->fresh()->isMemberOf($this->organization->id))->toBeFalse()
        ->and(OrganizationInvitation::query()->count())->toBe(0);
});

it('does not let an account of another organization\'s single sign-on join', function (): void {
    $other = Organization::factory()->create();
    $existing = app(OrganizationContext::class)->runAs($other, function () {
        $user = User::factory()->create(['email' => 'bob@example.com', 'auth_source' => AuthSource::Sso, 'password' => null]);
        $provider = SsoProvider::query()->create(['protocol' => 'oidc', 'name' => 'Their IdP', 'issuer' => 'https://idp.example.test', 'client_id' => 'x', 'client_secret' => 'y', 'allowed_domains' => ['example.com']]);
        (new SsoIdentity)->forceFill(['provider_id' => $provider->id, 'subject' => 'bob', 'user_id' => $user->id])->save();

        return $user;
    });
    Livewire::test(ManageUsers::class)->callAction('invite', data: ['name' => 'Bob', 'email' => 'bob@example.com', 'role_ids' => [roleId(DefaultRole::Agent)]]);

    app(OrganizationContext::class)->set($other);
    $this->actingAs($existing);
    Livewire::test(Invitations::class)->call('accept', OrganizationInvitation::query()->sole()->id)->assertSee(__('core::invitations.refused_sso'));

    expect($existing->fresh()->isMemberOf($this->organization->id))->toBeFalse();
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

it('lists this organization\'s pending invitations by address only, and cancels them', function (): void {
    $other = Organization::factory()->create();
    $existing = app(OrganizationContext::class)->runAs($other, fn () => User::factory()->create(['email' => 'bob@example.com', 'name' => 'Private Name']));
    Livewire::test(ManageUsers::class)->callAction('invite', data: ['name' => 'Typed', 'email' => 'bob@example.com', 'role_ids' => [roleId(DefaultRole::Agent)]]);
    $theirs = app(OrganizationContext::class)->runAs($other, function () use ($other) {
        $stranger = User::factory()->create(['email' => 'theirs@example.com']);

        return (new OrganizationInvitation)->forceFill(['organization_id' => $other->id, 'user_id' => $stranger->id, 'role_ids' => [], 'expires_at' => now()->addDay()]);
    });
    $theirs->save();
    $invitation = OrganizationInvitation::query()->where('organization_id', $this->organization->id)->sole();

    Livewire::test(ListOrganizationInvitations::class)
        ->assertSee('bob@example.com')->assertDontSee('Private Name')->assertDontSee('theirs@example.com')
        ->callTableAction('cancel', $invitation);

    expect(OrganizationInvitation::query()->pluck('id')->all())->toBe([$theirs->id])
        ->and(AuditLog::query()->where('event', AuditEvent::Invited)->where('auditable_id', $existing->id)->get()->contains(fn (AuditLog $log): bool => ($log->new_values['cancelled'] ?? false) === true))->toBeTrue();

    $this->actingAs(User::factory()->memberOf($this->organization)->withTwoFactor()->create());
    $this->get('/admin/invitations')->assertForbidden();
});

it('tells the person about waiting invitations on their account page', function (): void {
    $other = Organization::factory()->create();
    $existing = app(OrganizationContext::class)->runAs($other, fn () => User::factory()->create(['email' => 'bob@example.com']));
    Livewire::test(ManageUsers::class)->callAction('invite', data: ['name' => 'Bob', 'email' => 'bob@example.com', 'role_ids' => [roleId(DefaultRole::Agent)]]);

    app(OrganizationContext::class)->set($other);
    $this->actingAs($existing);
    Livewire::test(AccountSecurity::class)->assertSee('An organization asked you to join it.')->assertSeeHtml(route('account.invitations'));
});

it('lets an operator reset and unlock a shared account from the server, audited', function (): void {
    $shared = User::factory()->memberOf($this->organization)->withTwoFactor()->create(['email' => 'shared@example.com', 'locked_at' => now(), 'failed_logins' => 10]);
    $shared->organizations()->attach(Organization::factory()->create());
    auth()->logout();
    app(OrganizationContext::class)->clear();

    $this->artisan('core:account:reset-mfa', ['email' => 'nobody@example.com', '--force' => true])->assertFailed();
    $this->artisan('core:account:reset-mfa', ['email' => 'shared@example.com'])->expectsConfirmation('Reset the second factor of shared@example.com? Check first that the request really comes from them.', 'no')->assertFailed();
    expect($shared->fresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->artisan('core:account:reset-mfa', ['email' => 'SHARED@example.com', '--force' => true])->assertSuccessful();
    $this->artisan('core:account:unlock', ['email' => 'shared@example.com'])->assertSuccessful();

    expect($shared->fresh())->two_factor_confirmed_at->toBeNull()->locked_at->toBeNull()->failed_logins->toBe(0);
    $reset = AuditLog::query()->where('event', AuditEvent::MfaReset)->where('auditable_id', $shared->id)->sole();
    $unlocked = AuditLog::query()->where('event', AuditEvent::AccountUnlocked)->where('auditable_id', $shared->id)->sole();
    expect($reset->new_values['by'])->toBe('operator')->and($reset->user_id)->toBeNull()
        ->and($unlocked->new_values['by'])->toBe('operator')->and($unlocked->user_id)->toBeNull();
});

it('leaves the name, second factor and lock of a shared account alone', function (): void {
    $shared = User::factory()->memberOf($this->organization)->withTwoFactor()->create(['name' => 'Bob Shared', 'locked_at' => now()]);
    $shared->organizations()->attach(Organization::factory()->create());

    Livewire::test(ManageUsers::class)
        ->assertTableActionHidden('reset_mfa', $shared)
        ->assertTableActionHidden('unlock', $shared)
        ->callTableAction('edit', $shared, data: ['name' => 'Renamed by another organization']);

    expect($shared->fresh())->name->toBe('Bob Shared')->two_factor_confirmed_at->not->toBeNull()->locked_at->not->toBeNull();
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

it('names every way an account can sign in', function (AuthSource $source): void {
    expect(Lang::has('core::users.sources.'.$source->value))->toBeTrue();
})->with(AuthSource::cases());
