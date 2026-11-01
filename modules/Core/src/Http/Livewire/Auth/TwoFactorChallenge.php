<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Application\Authentication\CompleteLogin;

/**
 * Second step of signing in: a code from the authenticator app, or a recovery code.
 */
#[Layout('core::components.layouts.guest')]
final class TwoFactorChallenge extends Component
{
    public string $code = '';

    public function mount(CompleteLogin $completeLogin): void
    {
        if ($completeLogin->pendingUser() === null) {
            $this->redirectRoute('login');
        }
    }

    public function verify(CompleteLogin $completeLogin): void
    {
        $this->validate(['code' => ['required', 'string', 'max:32']]);

        try {
            $completeLogin->withCode($this->code);
        } catch (ValidationException $exception) {
            $this->reset('code');

            throw $exception;
        }

        $this->redirectIntended(route('home'));
    }

    public function render(): View
    {
        return view('core::livewire.auth.two-factor-challenge')->title(__('core::security.challenge.title'));
    }
}
