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
use Modules\Core\Domain\Directory\Directory;

#[Layout('core::components.layouts.guest')]
final class Login extends Component
{
    /** Email, or AD username / DOMAIN\username / UPN. */
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    public function login(AttemptLogin $attemptLogin, Request $request): void
    {
        $this->login = trim($this->login);
        $this->validate();

        try {
            $attemptLogin->handle($request, $this->login, $this->password, $this->remember);
        } catch (ValidationException $exception) {
            // Never send the typed password back in the component snapshot.
            $this->reset('password');

            throw $exception;
        }

        $this->redirectIntended(route('home'));
    }

    public function render(): View
    {
        return view('core::livewire.auth.login', [
            'directoryEnabled' => app(Directory::class)->isEnabled(),
        ])->title(__('core::auth.login.title'));
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['boolean'],
        ];
    }
}
