<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Livewire\Account\AccountSecurity;
use Modules\Core\Http\Livewire\Auth\Login;
use Modules\Core\Http\Livewire\Auth\TwoFactorChallenge;
use PragmaRX\Google2FA\Google2FA;

const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->memberOf($this->organization)->withTwoFactor()->create(['email' => 'jane@example.com', 'password' => 'correct-horse-battery']);
});

function role(User $user, Organization $organization, DefaultRole $role): void
{
    app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));
}

function otp(int $offsetSteps = 0): string
{
    return (new Google2FA)->oathTotp(SECRET, (int) floor(time() / 30) + $offsetSteps);
}

function passwordStep(): void
{
    Livewire::test(Login::class)->set('login', 'jane@example.com')->set('password', 'correct-horse-battery')->call('submit')
        ->assertRedirect(route('login.challenge'));
}

describe('signing in', function (): void {
    it('asks for a code after the password and signs in only then', function (): void {
        passwordStep();
        $this->assertGuest();

        Livewire::test(TwoFactorChallenge::class)->set('code', otp())->call('verify')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($this->user);
        expect(AuditLog::query()->where('event', AuditEvent::Login)->where('user_id', $this->user->id)->exists())->toBeTrue();
    });

    it('never accepts the same code twice', function (): void {
        $code = otp();
        passwordStep();
        Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasNoErrors();
        auth()->logout();

        passwordStep();
        Livewire::test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasErrors(['code']);
        $this->assertGuest();
    });

    it('refuses wrong codes, audits them, and stops after five a minute', function (): void {
        passwordStep();

        foreach (range(1, 5) as $ignored) {
            Livewire::test(TwoFactorChallenge::class)->set('code', '000000')->call('verify')->assertHasErrors(['code']);
        }

        Livewire::test(TwoFactorChallenge::class)->set('code', otp())->call('verify')->assertHasErrors(['code']);

        $this->assertGuest();
        expect(AuditLog::query()->where('event', AuditEvent::MfaFailed)->count())->toBe(5);
    });

    it('accepts each recovery code once', function (): void {
        $codes = app(TwoFactorAuthentication::class)->regenerateRecoveryCodes($this->user);

        passwordStep();
        Livewire::test(TwoFactorChallenge::class)->set('code', strtoupper($codes[0]))->call('verify')->assertHasNoErrors();
        auth()->logout();

        passwordStep();
        Livewire::test(TwoFactorChallenge::class)->set('code', $codes[0])->call('verify')->assertHasErrors(['code']);

        expect(app(TwoFactorAuthentication::class)->remainingRecoveryCodes($this->user->fresh()))->toBe(9)
            ->and(AuditLog::query()->where('event', AuditEvent::MfaRecoveryCodeUsed)->count())->toBe(1);
    });

    it('expires the pending sign-in after five minutes', function (): void {
        passwordStep();
        $this->travel(6)->minutes();

        Livewire::test(TwoFactorChallenge::class)->assertRedirect(route('login'));
    });

    it('signs in at once without two-factor authentication', function (): void {
        $plain = User::factory()->memberOf($this->organization)->create(['email' => 'bob@example.com', 'password' => 'correct-horse-battery']);

        Livewire::test(Login::class)->set('login', 'bob@example.com')->set('password', 'correct-horse-battery')->call('submit')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($plain);
    });
});

describe('who must use it', function (): void {
    it('sends staff without it to set it up, and leaves requesters alone', function (): void {
        $agent = User::factory()->memberOf($this->organization)->create();
        role($agent, $this->organization, DefaultRole::Agent);
        $requester = User::factory()->memberOf($this->organization)->create();
        role($requester, $this->organization, DefaultRole::Requester);

        $this->actingAs($agent)->get(route('home'))->assertRedirect(route('account.security'));
        $this->actingAs($agent)->get(route('account.security'))->assertOk()->assertSee(__('core::security.required'));
        $this->actingAs($requester)->get(route('home'))->assertOk();

        $admin = User::factory()->memberOf($this->organization)->create();
        role($admin, $this->organization, DefaultRole::Administrator);
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('account.security'));
    });

    it('counts a staff role in any organization', function (): void {
        $user = User::factory()->memberOf($this->organization)->create();
        role($user, Organization::factory()->create(), DefaultRole::CabMember);

        expect(app(TwoFactorAuthentication::class)->isRequiredFor($user))->toBeTrue();
    });
});

describe('setting it up', function (): void {
    it('turns it on after a valid code, stores the secret encrypted and shows ten recovery codes', function (): void {
        $user = User::factory()->memberOf($this->organization)->create();
        $this->actingAs($user);

        $component = Livewire::test(AccountSecurity::class)->call('startSetup')->assertSee('data:image/svg+xml', false);
        $secret = session('account.mfa_setup_secret');

        $component->set('code', '000000')->call('enable')->assertHasErrors(['code']);
        $component->set('code', (new Google2FA)->getCurrentOtp($secret))->call('enable')->assertHasNoErrors();

        expect($component->get('recoveryCodes'))->toHaveCount(10)
            ->and($user->fresh()->two_factor_confirmed_at)->not->toBeNull()
            ->and(DB::table('users')->where('id', $user->id)->value('two_factor_secret'))->not->toContain($secret)
            ->and(AuditLog::query()->where('event', AuditEvent::MfaEnabled)->where('auditable_id', $user->id)->exists())->toBeTrue()
            ->and(AuditLog::query()->where('auditable_id', $user->id)->get()->toJson())->not->toContain($secret);
    });

    it('turns off only with a valid code, and staff must then set it up again', function (): void {
        $agent = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
        role($agent, $this->organization, DefaultRole::Agent);
        $this->actingAs($agent);

        Livewire::test(AccountSecurity::class)->set('code', '123456')->call('disable')->assertHasErrors(['code']);
        expect(app(TwoFactorAuthentication::class)->isEnabled($agent->fresh()))->toBeTrue();

        Livewire::test(AccountSecurity::class)->assertSee(__('core::security.mfa.new_phone'))->set('code', otp())->call('disable')->assertHasNoErrors();

        expect(app(TwoFactorAuthentication::class)->isEnabled($agent->fresh()))->toBeFalse()
            ->and(AuditLog::query()->where('event', AuditEvent::MfaDisabled)->where('auditable_id', $agent->id)->exists())->toBeTrue();
        $this->get(route('home'))->assertRedirect(route('account.security'));
    });
});
