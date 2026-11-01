<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Application\Notifications\PasswordLink;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Livewire\Account\AccountSecurity;
use Modules\Core\Http\Livewire\Auth\ForgotPassword;
use Modules\Core\Http\Livewire\Auth\ResetPassword;

beforeEach(function (): void {
    Notification::fake();
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->memberOf($this->organization)->create(['email' => 'jane@example.com', 'password' => 'correct-horse-battery']);
});

describe('forgot password', function (): void {
    it('emails a link to local accounts, and answers the same for anyone else', function (string $email): void {
        Livewire::test(ForgotPassword::class)->set('email', $email)->call('send')->assertSet('sent', true)->assertHasNoErrors();
    })->with(['jane@example.com', 'nobody@example.com', 'ad.user@example.com']);

    it('sends the link only to active local accounts', function (): void {
        $directory = User::factory()->fromDirectory()->memberOf($this->organization)->create(['email' => 'ad.user@example.com']);
        $inactive = User::factory()->inactive()->memberOf($this->organization)->create(['email' => 'gone@example.com']);

        foreach (['JANE@example.com', 'ad.user@example.com', 'gone@example.com'] as $email) {
            Livewire::test(ForgotPassword::class)->set('email', $email)->call('send');
        }

        Notification::assertSentTo($this->user, PasswordLink::class, fn (PasswordLink $link): bool => ! $link->invitation);
        Notification::assertNotSentTo([$directory, $inactive], PasswordLink::class);
    });
});

describe('reset', function (): void {
    function resetToken(User $user): string
    {
        $token = '';
        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('send');
        Notification::assertSentTo($user, PasswordLink::class, function (PasswordLink $link) use (&$token): bool {
            $token = $link->token;

            return true;
        });

        return $token;
    }

    it('sets a new password from the link, once, and signs out other sessions', function (): void {
        $token = resetToken($this->user);
        $rememberToken = $this->user->fresh()->remember_token;

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'jane@example.com')->set('password', 'a brand new passphrase')->set('passwordConfirmation', 'a brand new passphrase')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $user = $this->user->fresh();
        expect(Hash::check('a brand new passphrase', $user->password))->toBeTrue()
            ->and($user->remember_token)->not->toBe($rememberToken)
            ->and(AuditLog::query()->where('event', AuditEvent::PasswordReset)->where('auditable_id', $user->id)->exists())->toBeTrue();

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'jane@example.com')->set('password', 'yet another passphrase')->set('passwordConfirmation', 'yet another passphrase')
            ->call('save')
            ->assertHasErrors(['email']);
    });

    it('refuses short passwords and keeps the link usable', function (): void {
        $token = resetToken($this->user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'jane@example.com')->set('password', 'short')->set('passwordConfirmation', 'short')
            ->call('save')
            ->assertHasErrors(['password']);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'jane@example.com')->set('password', 'a long enough passphrase')->set('passwordConfirmation', 'a long enough passphrase')
            ->call('save')
            ->assertHasNoErrors();
    });

    it('refuses a forged token', function (): void {
        Livewire::test(ResetPassword::class, ['token' => str_repeat('a', 64)])
            ->set('email', 'jane@example.com')->set('password', 'a long enough passphrase')->set('passwordConfirmation', 'a long enough passphrase')
            ->call('save')
            ->assertHasErrors(['email']);

        expect(Hash::check('correct-horse-battery', $this->user->fresh()->password))->toBeTrue();
    });
});

describe('change password', function (): void {
    it('needs the current password, and signs out other sessions', function (): void {
        $this->actingAs($this->user);
        $oldSessionHash = auth()->guard('web')->hashPasswordForCookie($this->user->password);

        Livewire::test(AccountSecurity::class)
            ->set('currentPassword', 'wrong')->set('newPassword', 'a brand new passphrase')->set('newPasswordConfirmation', 'a brand new passphrase')
            ->call('changePassword')
            ->assertHasErrors(['currentPassword']);

        Livewire::test(AccountSecurity::class)
            ->set('currentPassword', 'correct-horse-battery')->set('newPassword', 'a brand new passphrase')->set('newPasswordConfirmation', 'a brand new passphrase')
            ->call('changePassword')
            ->assertHasNoErrors();

        expect(AuditLog::query()->where('event', AuditEvent::PasswordChanged)->count())->toBe(1);

        // Another browser still holding the old password hash is signed out.
        $this->actingAs($this->user->fresh())->withSession(['password_hash_web' => $oldSessionHash])->get(route('home'))->assertRedirect(route('login'));
    });

    it('has nothing to change for Active Directory accounts', function (): void {
        $directory = User::factory()->fromDirectory()->memberOf($this->organization)->create();

        $this->actingAs($directory)->get(route('account.security'))->assertOk()->assertSee(__('core::security.password.directory'));
    });
});
