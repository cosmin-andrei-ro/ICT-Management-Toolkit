<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Application\Authentication\AccountLockout;
use Modules\Core\Application\Authentication\SetPassword;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Notifications\AccountLocked;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\SecuritySettings;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Pages\SecuritySettingsPage;
use Modules\Core\Http\Filament\Resources\Users\Pages\ManageUsers;
use Modules\Core\Http\Livewire\Auth\Login;
use Modules\Core\Http\Livewire\Auth\TwoFactorChallenge;

beforeEach(function (): void {
    Notification::fake();
    config(['core.authentication.lockout_after' => 3]);
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->memberOf($this->organization)->create(['email' => 'jane@example.com', 'password' => 'correct-horse-battery']);
});

function tryPassword(string $password): Testable
{
    return Livewire::test(Login::class)->set('login', 'jane@example.com')->set('password', $password)->call('submit');
}

describe('account lockout', function (): void {
    it('locks the account after consecutive failures, tells the person, and then refuses even the right password', function (): void {
        tryPassword('wrong-1')->assertHasErrors(['login']);
        tryPassword('wrong-2')->assertHasErrors(['login']);
        expect($this->user->fresh()->locked_at)->toBeNull();

        tryPassword('wrong-3')->assertHasErrors(['login']);
        expect($this->user->fresh()->locked_at)->not->toBeNull()
            ->and(AuditLog::query()->where('event', AuditEvent::AccountLocked)->where('auditable_id', $this->user->id)->exists())->toBeTrue();
        Notification::assertSentTo($this->user, AccountLocked::class);

        tryPassword('correct-horse-battery')->assertHasErrors(['login' => __('auth.failed')]);
        $this->assertGuest();
        expect(AuditLog::query()->where('event', AuditEvent::LoginFailed)->where('new_values->reason', 'locked')->exists())->toBeTrue();
    });

    it('counts consecutive failures only: a successful sign-in starts again', function (): void {
        tryPassword('wrong-1');
        tryPassword('wrong-2');
        tryPassword('correct-horse-battery')->assertHasNoErrors();
        auth()->logout();
        tryPassword('wrong-3');

        expect($this->user->fresh())->failed_logins->toBe(1)->locked_at->toBeNull();
    });

    it('counts wrong second-factor codes too, and ends a pending sign-in once locked', function (): void {
        $this->user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        tryPassword('correct-horse-battery')->assertRedirect(route('login.challenge'));

        foreach (range(1, 3) as $attempt) {
            Livewire::test(TwoFactorChallenge::class)->set('code', '000000')->call('verify')->assertHasErrors(['code']);
        }

        expect($this->user->fresh()->locked_at)->not->toBeNull();
        // The pending sign-in is over: back to the password, which fails while locked.
        Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));
        tryPassword('correct-horse-battery')->assertHasErrors(['login']);
        $this->assertGuest();
    });

    it('is lifted by a password reset, which proves control of the mailbox', function (): void {
        foreach (['a', 'b', 'c'] as $wrong) {
            tryPassword('wrong-'.$wrong);
        }

        app(SetPassword::class)->reset($this->user->fresh(), 'a-brand-new-passphrase', 'a-brand-new-passphrase');

        expect($this->user->fresh())->locked_at->toBeNull()->failed_logins->toBe(0)
            ->and(AuditLog::query()->where('event', AuditEvent::AccountUnlocked)->where('new_values->by', 'password_reset')->exists())->toBeTrue();
        tryPassword('a-brand-new-passphrase')->assertHasNoErrors();
    });

    it('is lifted by an administrator of the person\'s organization', function (): void {
        Filament::setCurrentPanel('admin');
        app(SyncPermissions::class)->handle();
        app(OrganizationContext::class)->set($this->organization);
        $admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
        $admin->assignRole(DefaultRole::Administrator->value);
        $this->user->forceFill(['locked_at' => now(), 'failed_logins' => 3])->save();

        $this->actingAs($admin);
        Livewire::test(ManageUsers::class)->assertSee('Unlock')->callTableAction('unlock', $this->user);

        expect($this->user->fresh()->locked_at)->toBeNull()
            ->and(AuditLog::query()->where('event', AuditEvent::AccountUnlocked)->where('user_id', $admin->id)->exists())->toBeTrue();
    });

    it('leaves Active Directory accounts to the directory, and can be turned off', function (): void {
        $lockout = app(AccountLockout::class);
        $directory = User::factory()->memberOf($this->organization)->create(['auth_source' => AuthSource::Ldap, 'password' => null, 'directory_guid' => (string) Str::uuid()]);

        foreach (range(1, 5) as $failure) {
            $lockout->recordFailure($directory);
        }
        expect($directory->fresh()->locked_at)->toBeNull();

        config(['core.authentication.lockout_after' => 0]);
        foreach (range(1, 5) as $failure) {
            $lockout->recordFailure($this->user);
        }
        expect($this->user->fresh()->locked_at)->toBeNull();
    });
});

describe('breached passwords', function (): void {
    beforeEach(function (): void {
        config(['core.authentication.check_breached_passwords' => true]);
    });

    it('refuses a password known from breaches; only a hash prefix leaves the server', function (): void {
        $hash = strtoupper(sha1('a-brand-new-passphrase'));
        Http::fake(['api.pwnedpasswords.com/range/*' => Http::response(substr($hash, 5).':42')]);

        expect(fn () => app(SetPassword::class)->reset($this->user, 'a-brand-new-passphrase', 'a-brand-new-passphrase'))->toThrow(ValidationException::class);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5));
    });

    it('accepts the password when the service does not answer', function (): void {
        Http::fake(fn () => throw new ConnectionException('unreachable'));

        app(SetPassword::class)->reset($this->user, 'a-brand-new-passphrase', 'a-brand-new-passphrase');

        expect(Hash::check('a-brand-new-passphrase', (string) $this->user->fresh()->password))->toBeTrue();
    });
});

describe('roles that must use two-factor authentication', function (): void {
    beforeEach(function (): void {
        app(SyncPermissions::class)->handle();
        app(OrganizationContext::class)->set($this->organization);
    });

    it('are every role but requester until the organization chooses', function (): void {
        $agent = User::factory()->memberOf($this->organization)->create();
        $agent->assignRole(DefaultRole::Agent->value);
        $requester = User::factory()->memberOf($this->organization)->create();
        $requester->assignRole(DefaultRole::Requester->value);
        $twoFactor = app(TwoFactorAuthentication::class);

        expect($twoFactor->isRequiredFor($agent))->toBeTrue()->and($twoFactor->isRequiredFor($requester))->toBeFalse();

        (new SecuritySettings)->forceFill(['mfa_required_roles' => [DefaultRole::Requester->value]])->save();
        expect($twoFactor->isRequiredFor($agent))->toBeFalse()->and($twoFactor->isRequiredFor($requester))->toBeTrue();
    });

    it('always include administrators, and follow each organization for its own roles', function (): void {
        (new SecuritySettings)->forceFill(['mfa_required_roles' => []])->save();
        $admin = User::factory()->memberOf($this->organization)->create();
        $admin->assignRole(DefaultRole::Administrator->value);
        $agent = User::factory()->memberOf($this->organization)->create();
        $agent->assignRole(DefaultRole::Agent->value);
        $twoFactor = app(TwoFactorAuthentication::class);

        expect($twoFactor->isRequiredFor($admin))->toBeTrue()->and($twoFactor->isRequiredFor($agent))->toBeFalse();

        // An agent in a second organization that kept the default must use it.
        $other = Organization::factory()->create();
        $agent->organizations()->attach($other);
        app(OrganizationContext::class)->set($other);
        setPermissionsTeamId($other->id);
        $agent->assignRole(DefaultRole::Agent->value);

        expect($twoFactor->isRequiredFor($agent->fresh()))->toBeTrue();
    });

    it('are chosen by administrators on the security settings page', function (): void {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
        $admin->assignRole(DefaultRole::Administrator->value);
        $lead = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
        $lead->assignRole(DefaultRole::TeamLead->value);

        $this->actingAs($lead)->get('/admin/security')->assertForbidden();

        $this->actingAs($admin)->get('/admin/security')->assertOk()->assertSee('3 failed sign-ins');
        Livewire::test(SecuritySettingsPage::class)
            ->assertSet('data.mfa_required_roles', fn (array $roles): bool => in_array('agent', $roles, true) && ! in_array('requester', $roles, true) && ! in_array('administrator', $roles, true))
            ->set('data.mfa_required_roles', ['team-lead', 'administrator', 'not-a-role'])
            ->call('save')->assertHasErrors();
        Livewire::test(SecuritySettingsPage::class)->set('data.mfa_required_roles', ['team-lead'])->call('save')->assertHasNoErrors();

        Livewire::test(SecuritySettingsPage::class)->assertSet('data.password_composition_required', false)->set('data.password_composition_required', true)->call('save')->assertHasNoErrors();
        Livewire::test(SecuritySettingsPage::class)->assertSet('data.password_composition_required', true);

        expect(SecuritySettings::current()->mfa_required_roles)->toBe(['team-lead'])
            ->and(AuditLog::query()->where('auditable_type', 'core.security_settings')->exists())->toBeTrue();
    });
});
