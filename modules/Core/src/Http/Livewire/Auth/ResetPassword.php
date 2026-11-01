<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Auth;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Application\Authentication\SetPassword;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\User;

/**
 * Choosing a password from an emailed link: a reset, or an invitation to a new
 * account (longer-lived link). Two-factor authentication still applies at sign-in.
 */
#[Layout('core::components.layouts.guest')]
final class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    #[Locked]
    public bool $invitation = false;

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitation = request()->routeIs('invitation.accept');
        $email = request()->query('email');
        $this->email = is_string($email) ? $email : '';
    }

    public function save(PasswordBrokerFactory $brokers, SetPassword $setPassword): void
    {
        $this->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:1024']]);

        $status = $brokers->broker($this->invitation ? 'invites' : 'users')->reset(
            [
                'email' => mb_strtolower(trim($this->email)),
                'auth_source' => AuthSource::Local->value,
                'is_active' => true,
                'token' => $this->token,
                'password' => $this->password,
                'password_confirmation' => $this->passwordConfirmation,
            ],
            function (User $user, string $password) use ($setPassword): void {
                $setPassword->reset($user, $password, $this->passwordConfirmation);
            },
        );

        $this->reset('password', 'passwordConfirmation');

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            $this->addError('email', __('core::security.reset.invalid_link'));

            return;
        }

        session()->flash('status', __('core::security.reset.done'));
        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('core::livewire.auth.reset-password')->title(__($this->invitation ? 'core::security.reset.welcome_title' : 'core::security.reset.title'));
    }
}
