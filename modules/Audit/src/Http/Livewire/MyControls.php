<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Audit\Domain\Models\Control;
use Modules\Core\Domain\Models\User;

/**
 * The controls the signed-in person owns, for owners outside the readers of compliance.
 */
#[Layout('core::components.layouts.app')]
final class MyControls extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return view('audit::livewire.controls.mine', [
            'controls' => Control::query()->ownedBy($user)->orderBy('number')->limit(200)->get(),
        ])->title(__('audit::compliance.mine.title'));
    }
}
