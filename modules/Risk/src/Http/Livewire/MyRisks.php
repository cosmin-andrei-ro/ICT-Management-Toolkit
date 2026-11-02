<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * The risks the signed-in person owns, for owners outside the register's readers.
 */
#[Layout('core::components.layouts.app')]
final class MyRisks extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return view('risk::livewire.risks.mine', [
            'risks' => Risk::query()->ownedBy($user)->orderBy('reference')->limit(200)->get(),
            'settings' => RiskSettings::current(),
        ])->title(__('risk::risks.mine.title'));
    }
}
