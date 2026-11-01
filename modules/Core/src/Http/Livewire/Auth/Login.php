<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Application\Authentication\AttemptLogin;

#[Layout('core::components.layouts.guest')]
final class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(AttemptLogin $attemptLogin, Request $request): void
    {
        $this->email = trim($this->email);
        $this->validate();

        try {
            $attemptLogin->handle($request, $this->email, $this->password, $this->remember);
        } catch (ValidationException $exception) {
            // Never send the typed password back in the component snapshot.
            $this->reset('password');

            throw $exception;
        }

        $this->redirectIntended(route('home'));
    }

    public function render(): View
    {
        return view('core::livewire.auth.login')->title(__('core::auth.login.title'));
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['boolean'],
        ];
    }
}
