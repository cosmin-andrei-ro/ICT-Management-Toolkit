<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Account;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Application\Authentication\SetPassword;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Models\User;

/**
 * The signed-in person's own security settings: two-factor authentication and,
 * for local accounts, their password.
 */
#[Layout('core::components.layouts.app')]
final class AccountSecurity extends Component
{
    /** The secret being set up is kept in the session, never in the page state. */
    private const string SETUP_SECRET = 'account.mfa_setup_secret';

    public string $code = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    /** @var list<string> Shown once, right after they were made. */
    public array $recoveryCodes = [];

    public bool $settingUp = false;

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

    public function regenerateRecoveryCodes(TwoFactorAuthentication $twoFactor): void
    {
        $this->confirmWithCode($twoFactor);
        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($this->user());
    }

    /**
     * Also how staff move to a new phone: they prove a second factor (app or
     * recovery code), and RequireTwoFactor then has them set it up again.
     */
    public function disable(TwoFactorAuthentication $twoFactor): void
    {
        $this->confirmWithCode($twoFactor);
        $twoFactor->disable($this->user());
        $this->recoveryCodes = [];
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
        ])->title(__('core::security.title'));
    }

    private function confirmWithCode(TwoFactorAuthentication $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:32']]);
        $valid = $twoFactor->verify($this->user(), $this->code);
        $this->reset('code');

        if (! $valid) {
            throw ValidationException::withMessages(['code' => __('core::security.challenge.invalid')]);
        }
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
