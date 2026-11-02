<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Application\Authentication\SecurityKeys;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\SecurityKey;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Livewire\Account\AccountSecurity;
use Modules\Core\Http\Livewire\Auth\Login;
use Modules\Core\Http\Livewire\Auth\TwoFactorChallenge;
use Modules\Core\Tests\Support\VirtualAuthenticator;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    $this->user = User::factory()->withTwoFactor()->create(['email' => 'kim@example.com', 'password' => 'correct-horse-battery']);
    $this->keys = app(SecurityKeys::class);
    $this->authenticator = new VirtualAuthenticator;
});

it('registers a key and accepts it as the second factor of its owner only', function (): void {
    $key = $this->keys->register($this->user, $this->authenticator->create($this->keys->creationOptions($this->user)), 'YubiKey');
    expect($key->is_passkey)->toBeTrue()->and($this->keys->has($this->user))->toBeTrue()
        ->and(AuditLog::query()->where('event', AuditEvent::SecurityKeyAdded)->exists())->toBeTrue();

    $options = $this->keys->requestOptions($this->user);
    expect($options['allowCredentials'] ?? [])->toHaveCount(1);
    expect($this->keys->authenticate($this->user, $this->authenticator->get($options, $this->user->id))->id)->toBe($this->user->id)
        ->and($key->fresh()?->last_used_at)->not->toBeNull();

    // The challenge is used once.
    expect(fn () => $this->keys->authenticate($this->user, $this->authenticator->get($options, $this->user->id)))->toThrow(InvalidArgumentException::class);

    // Someone else's second factor.
    $other = User::factory()->withTwoFactor()->create();
    $options = $this->keys->requestOptions($other);
    expect(fn () => $this->keys->authenticate($other, $this->authenticator->get($options, $this->user->id)))->toThrow(InvalidArgumentException::class);
});

it('signs in with a passkey only when the person was verified on the device', function (): void {
    $this->keys->register($this->user, $this->authenticator->create($this->keys->creationOptions($this->user)), 'Laptop');

    $options = $this->keys->requestOptions(null);
    expect($options['allowCredentials'] ?? [])->toBe([])->and($options['userVerification'])->toBe('required');
    expect($this->keys->authenticate(null, $this->authenticator->get($options, $this->user->id))->id)->toBe($this->user->id);

    $options = $this->keys->requestOptions(null);
    expect(fn () => $this->keys->authenticate(null, $this->authenticator->get($options, $this->user->id, verified: false)))->toThrow(InvalidArgumentException::class);

    // A deactivated account cannot sign in with its passkey.
    $this->user->forceFill(['is_active' => false])->save();
    $options = $this->keys->requestOptions(null);
    expect(fn () => $this->keys->authenticate(null, $this->authenticator->get($options, $this->user->id)))->toThrow(InvalidArgumentException::class);
});

it('refuses answers made for another site or a replayed counter', function (): void {
    expect(fn () => $this->keys->register($this->user, $this->authenticator->create($this->keys->creationOptions($this->user), origin: 'https://phishing.example'), 'Key'))
        ->toThrow(InvalidArgumentException::class);
    expect(SecurityKey::query()->count())->toBe(0);

    $this->keys->register($this->user, $this->authenticator->create($this->keys->creationOptions($this->user)), 'Key');
    expect(fn () => $this->keys->authenticate($this->user, $this->authenticator->get($this->keys->requestOptions($this->user), $this->user->id, origin: 'https://phishing.example')))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * A code of the factory's authenticator secret, a given number of 30-second steps ahead (each is accepted once).
 */
function keyTestCode(int $step = 0): string
{
    return (new Google2FA)->oathTotp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', (int) floor(time() / 30) + $step);
}

/**
 * @return array<string, mixed>
 */
function pendingOptions(string $slot): array
{
    $json = session($slot);

    return is_string($json) ? json_decode($json, true, 32, JSON_THROW_ON_ERROR) : [];
}

it('adds a key from the account page after a code, and removes it the same way', function (): void {
    $this->actingAs($this->user);
    $page = Livewire::test(AccountSecurity::class)
        ->set('keyName', 'YubiKey')->set('keyCode', '000000')->call('keyOptions')->assertHasErrors('keyCode');
    expect(session('webauthn.creation'))->toBeNull();

    // A key cannot be added without the step that proves the second factor.
    $page->call('addKey', $this->authenticator->create($this->keys->creationOptions($this->user)))->assertHasErrors('keyCode');
    expect(SecurityKey::query()->count())->toBe(0);

    $page = Livewire::test(AccountSecurity::class)->set('keyName', 'YubiKey')->set('keyCode', keyTestCode())->call('keyOptions')->assertHasNoErrors();
    $page->call('addKey', $this->authenticator->create(pendingOptions('webauthn.creation')))->assertHasNoErrors()
        ->assertSet('keyAdded', true)->assertSee('YubiKey')->assertSee('Passkey');
    $key = SecurityKey::query()->sole();

    $page->set('keyCode', 'wrong')->call('removeKey', $key->id)->assertHasErrors('keyCode');
    $page->set('keyCode', keyTestCode(1))->call('removeKey', $key->id)->assertHasNoErrors();
    expect(SecurityKey::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', AuditEvent::SecurityKeyRemoved)->exists())->toBeTrue();
});

it('signs in with the password and a key instead of a code', function (): void {
    $this->keys->register($this->user, $this->authenticator->create($this->keys->creationOptions($this->user)), 'YubiKey');

    Livewire::test(Login::class)->set('login', 'kim@example.com')->set('password', 'correct-horse-battery')->call('submit')->assertRedirect(route('login.challenge'));
    $challenge = Livewire::test(TwoFactorChallenge::class)->assertSee('Use your security key or passkey')->call('keyOptions');
    $this->assertGuest();

    $challenge->call('useKey', $this->authenticator->get(pendingOptions('webauthn.request'), $this->user->id))->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($this->user);
});

it('signs in with a passkey alone from the login page, and refuses a bad answer', function (): void {
    $this->keys->register($this->user, $this->authenticator->create($this->keys->creationOptions($this->user)), 'Laptop');

    $login = Livewire::test(Login::class)->assertSee('Sign in with a passkey')->call('passkeyOptions');
    $login->call('signInWithPasskey', $this->authenticator->get(pendingOptions('webauthn.request'), $this->user->id, verified: false))->assertHasErrors('login');
    $this->assertGuest();
    expect(AuditLog::query()->where('event', AuditEvent::LoginFailed)->exists())->toBeTrue();

    $login->call('passkeyOptions')->call('signInWithPasskey', $this->authenticator->get(pendingOptions('webauthn.request'), $this->user->id))->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($this->user);
});

it('removes the keys when two-factor authentication is turned off or reset', function (): void {
    $this->keys->register($this->user, $this->authenticator->create($this->keys->creationOptions($this->user)), 'YubiKey');
    $admin = User::factory()->create();

    app(TwoFactorAuthentication::class)->disable($this->user, $admin);
    expect(SecurityKey::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', AuditEvent::SecurityKeyRemoved)->where('user_id', $admin->id)->exists())->toBeTrue();
});
