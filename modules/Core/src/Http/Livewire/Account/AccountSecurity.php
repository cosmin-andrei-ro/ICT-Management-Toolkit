<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Account;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Application\Authentication\ConfirmSecondFactor;
use Modules\Core\Application\Authentication\SecurityKeys;
use Modules\Core\Application\Authentication\SetPassword;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Models\OrganizationInvitation;
use Modules\Core\Domain\Models\SecurityKey;
use Modules\Core\Domain\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in person's own security settings: two-factor authentication,
 * security keys and passkeys, and, for local accounts, their password.
 */
#[Layout('core::components.layouts.app')]
final class AccountSecurity extends Component
{
    /** The secret being set up is kept in the session, never in the page state. */
    private const string SETUP_SECRET = 'account.mfa_setup_secret';

    /** Until when a key may be added, once the person proved their second factor. */
    private const string KEY_ALLOWED_UNTIL = 'account.key_allowed_until';

    public string $code = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    /** @var list<string> Shown once, right after they were made. */
    public array $recoveryCodes = [];

    public bool $settingUp = false;

    public string $keyName = '';

    /** The second factor proving it is them, to add or remove a key. */
    public string $keyCode = '';

    public bool $keyAdded = false;

    public function startSetup(TwoFactorAuthentication $twoFactor): void
    {
        session()->put(self::SETUP_SECRET, $twoFactor->newSecret());
        $this->settingUp = true;
        $this->reset('code');
    }

    public function enable(TwoFactorAuthentication $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:16']]);
        $secret = session()->get(self::SETUP_SECRET);

        if (! is_string($secret) || $twoFactor->isEnabled($this->user())) {
            $this->settingUp = false;

            return;
        }

        $codes = $twoFactor->enable($this->user(), $secret, $this->code);
        $this->reset('code');

        if ($codes === null) {
            $this->addError('code', __('core::security.challenge.invalid'));

            return;
        }

        session()->forget(self::SETUP_SECRET);
        $this->settingUp = false;
        $this->recoveryCodes = $codes;
    }

    public function regenerateRecoveryCodes(TwoFactorAuthentication $twoFactor, ConfirmSecondFactor $confirm): void
    {
        $this->confirmWithCode($confirm, 'regenerate_recovery_codes');
        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($this->user());
    }

    /**
     * A text file of the codes on screen, so they can go straight into a password
     * manager or a safe. Only the codes this page already shows are written.
     */
    public function downloadRecoveryCodes(): ?StreamedResponse
    {
        if ($this->recoveryCodes === []) {
            return null;
        }

        $lines = [
            __('core::security.mfa.recovery_file_heading', ['app' => config()->string('app.name'), 'account' => $this->user()->email]),
            __('core::security.mfa.recovery_file_made', ['date' => now()->toDateString()]),
            '',
            ...$this->recoveryCodes,
        ];

        return response()->streamDownload(function () use ($lines): void {
            echo implode(PHP_EOL, $lines).PHP_EOL;
        }, 'recovery-codes.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Also how staff move to a new phone: they prove a second factor (app or
     * recovery code), and RequireTwoFactor then has them set it up again.
     */
    public function disable(TwoFactorAuthentication $twoFactor, ConfirmSecondFactor $confirm): void
    {
        $this->confirmWithCode($confirm, 'disable_mfa');
        $twoFactor->disable($this->user());
        $this->recoveryCodes = [];
    }

    /**
     * @return ?array<string, mixed> The options for the browser, once the person proved their second factor.
     */
    public function keyOptions(TwoFactorAuthentication $twoFactor, ConfirmSecondFactor $confirm, SecurityKeys $keys): ?array
    {
        $this->keyAdded = false;
        $this->validate(['keyName' => ['required', 'string', 'max:100'], 'keyCode' => ['required', 'string', 'max:32']]);

        if (! $twoFactor->isEnabled($this->user())) {
            return null;
        }

        $this->confirmKeyCode($confirm, 'add_security_key');
        session()->put(self::KEY_ALLOWED_UNTIL, now()->addMinutes(5)->getTimestamp());

        return $keys->creationOptions($this->user());
    }

    public function addKey(string $credential, SecurityKeys $keys): void
    {
        $allowedUntil = session()->pull(self::KEY_ALLOWED_UNTIL);

        if (! is_int($allowedUntil) || $allowedUntil < now()->getTimestamp()) {
            $this->addError('keyCode', __('core::security.challenge.expired'));

            return;
        }

        try {
            $keys->register($this->user(), $credential, $this->keyName);
        } catch (InvalidArgumentException) {
            $this->addError('keyName', __('core::security.keys.failed'));

            return;
        }

        $this->reset('keyName');
        $this->keyAdded = true;
    }

    public function removeKey(string $keyId, ConfirmSecondFactor $confirm, SecurityKeys $keys): void
    {
        $this->confirmKeyCode($confirm, 'remove_security_key');
        $keys->remove(SecurityKey::query()->with('user')->where('user_id', $this->user()->id)->findOrFail($keyId), $this->user());
    }

    public function changePassword(SetPassword $setPassword): void
    {
        try {
            $setPassword->change($this->user(), $this->currentPassword, $this->newPassword, $this->newPasswordConfirmation);
        } catch (ValidationException $exception) {
            $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');

            $errors = [];

            foreach ($exception->errors() as $key => $messages) {
                $errors[$key === 'current_password' ? 'currentPassword' : 'newPassword'] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        session()->flash('password_changed', __('core::security.password.changed'));
    }

    public function render(TwoFactorAuthentication $twoFactor): View
    {
        $user = $this->user();
        $secret = $this->settingUp ? session()->get(self::SETUP_SECRET) : null;

        return view('core::livewire.account.security', [
            'user' => $user,
            'enabled' => $twoFactor->isEnabled($user),
            'required' => $twoFactor->isRequiredFor($user),
            'remaining' => $twoFactor->remainingRecoveryCodes($user),
            'secret' => is_string($secret) ? $secret : null,
            'qrCode' => is_string($secret) ? $twoFactor->qrCode($user, $secret) : null,
            'pendingInvitations' => OrganizationInvitation::pendingFor($user)->count(),
            'securityKeys' => SecurityKey::query()->where('user_id', $user->id)->orderBy('created_at')->get(),
        ])->title(__('core::security.title'));
    }

    private function confirmWithCode(ConfirmSecondFactor $confirm, string $purpose): void
    {
        $this->validate(['code' => ['required', 'string', 'max:32']]);
        $code = $this->code;
        $this->reset('code');
        $confirm->confirm($this->user(), $code, $purpose, 'code');
    }

    private function confirmKeyCode(ConfirmSecondFactor $confirm, string $purpose): void
    {
        $this->validate(['keyCode' => ['required', 'string', 'max:32']]);
        $code = $this->keyCode;
        $this->reset('keyCode');
        $confirm->confirm($this->user(), $code, $purpose, 'keyCode');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
