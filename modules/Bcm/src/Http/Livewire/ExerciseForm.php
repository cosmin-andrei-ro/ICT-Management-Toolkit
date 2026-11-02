<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bcm\Application\Exercises\PlanExercise;
use Modules\Bcm\Domain\Enums\ExerciseType;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Plans an exercise of a plan in force.
 */
#[Layout('core::components.layouts.app')]
final class ExerciseForm extends Component
{
    #[Locked]
    public string $planId = '';

    public string $type = 'tabletop';

    public string $scenario = '';

    public string $title = '';

    public string $scheduledOn = '';

    public string $facilitatorId = '';

    public string $participants = '';

    public function mount(Plan $plan): void
    {
        $this->authorize('exercise', $plan);
        $this->planId = $plan->id;
        $this->scenario = $plan->versions()->where('status', 'approved')->first()->scenarios[0] ?? '';
        $this->facilitatorId = (string) auth()->id();
    }

    public function save(PlanExercise $planExercise, OrganizationContext $context): void
    {
        $plan = Plan::query()->findOrFail($this->planId);
        $this->authorize('exercise', $plan);

        $this->validate([
            'type' => ['required', Rule::enum(ExerciseType::class)],
            'scenario' => ['nullable', Rule::enum(Scenario::class)],
            'title' => ['required', 'string', 'max:255'],
            'scheduledOn' => ['required', 'date_format:Y-m-d'],
            'facilitatorId' => ['nullable', 'uuid', Rule::in($this->people($context)->pluck('id')->all())],
            'participants' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $exercise = $planExercise->handle($plan, [
                'type' => $this->type,
                'scenario' => $this->scenario,
                'title' => $this->title,
                'scheduled_on' => $this->scheduledOn,
                'facilitator_id' => $this->facilitatorId,
                'participants' => $this->participants,
            ]);
        } catch (InvalidArgumentException) {
            $this->addError('title', __('bcm::exercises.errors.not_in_force'));

            return;
        }

        $this->redirectRoute('bcm.exercises.show', $exercise);
    }

    public function render(OrganizationContext $context): View
    {
        return view('bcm::livewire.exercises.form', [
            'plan' => Plan::query()->findOrFail($this->planId),
            'types' => ExerciseType::cases(),
            'scenarios' => Scenario::cases(),
            'people' => $this->people($context),
        ])->title(__('bcm::exercises.ui.new'));
    }

    /**
     * @return Collection<int, User>
     */
    private function people(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }
}
