<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bcm\Application\Actions\StartPlanVersion;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Models\User;

/**
 * One plan: owners, the version in force and every version.
 *
 * @property-read Plan $plan Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowPlan extends Component
{
    #[Locked]
    public string $planId = '';

    public function mount(Plan $plan): void
    {
        $this->authorize('view', $plan);
        $this->planId = $plan->id;
    }

    #[Computed]
    public function plan(): Plan
    {
        return Plan::query()->with(['owner', 'deputy'])->findOrFail($this->planId);
    }

    public function startVersion(StartPlanVersion $start): void
    {
        $this->authorize('edit', $this->plan);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);
        $this->redirectRoute('bcm.versions.show', $start->handle($this->plan, $actor));
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->plan);
        $this->plan->delete();
        $this->redirectRoute('bcm.plans.index');
    }

    public function render(): View
    {
        $plan = $this->plan;

        return view('bcm::livewire.plans.show', [
            'plan' => $plan,
            'versions' => $plan->versions()->with(['submitter', 'decider'])->get(),
            'exercises' => $plan->exercises()->limit(50)->get(),
        ])->title($plan->name);
    }
}
