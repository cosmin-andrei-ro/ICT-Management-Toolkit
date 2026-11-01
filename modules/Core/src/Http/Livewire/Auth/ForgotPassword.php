<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Auth;

use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Directory\AuthSource;

/**
 * "Forgot password" for local accounts. Always the same answer, whether or not
 * the address has an account, so it cannot be used to find accounts.
 */
#[Layout('core::components.layouts.guest')]
final class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(PasswordBrokerFactory $brokers, Request $request): void
    {
        $this->validate(['email' => ['required', 'email', 'max:255']]);

        $key = 'forgot-password:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        RateLimiter::hit($key, 60);

        // Local, active accounts only; the broker's own throttle limits one email per minute.
        $brokers->broker('users')->sendResetLink([
            'email' => mb_strtolower(trim($this->email)),
            'auth_source' => AuthSource::Local->value,
            'is_active' => true,
        ]);

        $this->reset('email');
        $this->sent = true;
    }

    public function render(): View
    {
        return view('core::livewire.auth.forgot-password')->title(__('core::security.forgot.title'));
    }
}
