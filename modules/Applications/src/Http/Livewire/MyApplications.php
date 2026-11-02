<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Applications\Domain\Models\Application;
use Modules\Core\Domain\Models\User;

/**
 * The applications the signed-in person owns, for owners outside IT.
 */
#[Layout('core::components.layouts.app')]
final class MyApplications extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return view('applications::livewire.applications.mine', [
            'applications' => Application::query()->ownedBy($user)->orderBy('name')->limit(200)->get(),
        ])->title(__('applications::applications.mine.title'));
    }
}
