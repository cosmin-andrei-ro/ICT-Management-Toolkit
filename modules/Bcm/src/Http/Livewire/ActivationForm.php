<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bcm\Application\Activations\ActivatePlan;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Http\Livewire\Concerns\LocalTimes;
use Modules\Core\Domain\Models\User;

/**
 * Records that a plan in force was activated, and tells its crisis team.
 */
#[Layout('core::components.layouts.app')]
final class ActivationForm extends Component
{
    use LocalTimes;

    #[Locked]
    public string $planId = '';

    public string $activatedAt = '';

    public string $scenario = '';

    public string $reason = '';

    public bool $notifyTeam = true;

    public function mount(Plan $plan): void
    {
        $this->authorize('activate', $plan);
        $this->planId = $plan->id;
        $this->activatedAt = $this->formatLocal(CarbonImmutable::now());
    }

    public function save(ActivatePlan $activate): void
    {
        $plan = Plan::query()->findOrFail($this->planId);
        $this->authorize('activate', $plan);

        $this->validate([
            'activatedAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'scenario' => ['nullable', Rule::enum(Scenario::class)],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);
        $at = $this->parseLocal($this->activatedAt);

        try {
            $activation = $activate->handle($plan, $at ?? CarbonImmutable::now(), Scenario::tryFrom($this->scenario), $this->reason, $this->notifyTeam, $actor);
        } catch (InvalidArgumentException) {
            $this->addError('activatedAt', __('bcm::activations.errors.invalid'));

            return;
        }

        $this->redirectRoute('bcm.activations.show', $activation);
    }

    public function render(): View
    {
        return view('bcm::livewire.activations.form', [
            'plan' => Plan::query()->findOrFail($this->planId),
            'scenarios' => Scenario::cases(),
        ])->title(__('bcm::activations.ui.new'));
    }
}
