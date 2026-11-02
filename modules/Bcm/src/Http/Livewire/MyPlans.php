<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Domain\Models\User;

/**
 * The plans the signed-in person owns or deputizes for.
 */
#[Layout('core::components.layouts.app')]
final class MyPlans extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $plans = Plan::query()->ownedBy($user)->where('status', 'active')->orderBy('name')->get();

        return view('bcm::livewire.plans.mine', [
            'plans' => $plans,
            'open' => PlanVersion::query()->whereIn('plan_id', $plans->modelKeys())->whereIn('status', ['draft', 'submitted', 'returned'])->get()->keyBy('plan_id'),
        ])->title(__('bcm::plans.mine.title'));
    }
}
