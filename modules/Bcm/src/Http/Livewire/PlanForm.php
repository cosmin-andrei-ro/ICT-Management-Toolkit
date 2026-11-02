<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bcm\Application\Policies\PlanPolicy;
use Modules\Bcm\Domain\Enums\PlanType;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

#[Layout('core::components.layouts.app')]
final class PlanForm extends Component
{
    #[Locked]
    public ?string $planId = null;

    public string $type = 'drp';

    public string $name = '';

    public string $description = '';

    public string $ownerId = '';

    public string $deputyId = '';

    public string $status = 'active';

    public function mount(PlanPolicy $policy, ?Plan $plan = null): void
    {
        $user = $this->actor();

        if ($plan === null || ! $plan->exists) {
            $this->authorize('create', Plan::class);
            // Team leads only write IT recovery plans.
            $this->type = $policy->manages($user, PlanType::Bcp) ? 'bcp' : 'drp';
            $this->ownerId = $user->id;

            return;
        }

        $this->authorize('update', $plan);
        $this->planId = $plan->id;
        $this->type = $plan->type->value;
        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->ownerId = $plan->owner_id ?? '';
        $this->deputyId = $plan->deputy_id ?? '';
        $this->status = $plan->status;
    }

    public function save(OrganizationContext $context, PlanPolicy $policy): void
    {
        $plan = $this->planId !== null ? Plan::query()->findOrFail($this->planId) : null;
        $this->authorize($plan !== null ? 'update' : 'create', $plan ?? Plan::class);
        $members = $this->members($context)->pluck('id')->all();

        $this->validate([
            'type' => ['required', Rule::enum(PlanType::class)],
            'name' => ['required', 'string', 'max:255', $this->uniqueName(...)],
            'description' => ['nullable', 'string', 'max:5000'],
            'ownerId' => ['required', 'uuid', Rule::in($members)],
            'deputyId' => ['nullable', 'uuid', Rule::in($members), 'different:ownerId'],
            'status' => ['required', Rule::in(['active', 'retired'])],
        ]);

        // A team lead may not create or turn a plan into a business continuity plan.
        if (! $policy->manages($this->actor(), PlanType::from($this->type))) {
            $this->addError('type', __('bcm::plans.errors.type'));

            return;
        }

        $plan ??= new Plan;
        $plan->fill([
            'type' => $this->type,
            'name' => trim($this->name),
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'owner_id' => $this->ownerId,
            'deputy_id' => $this->deputyId !== '' ? $this->deputyId : null,
            'status' => $this->status,
        ])->save();

        $this->redirectRoute('bcm.plans.show', $plan);
    }

    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = is_string($value) && Plan::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
            ->when($this->planId !== null, fn ($query) => $query->whereKeyNot($this->planId))
            ->exists();

        if ($taken) {
            $fail(__('bcm::plans.errors.name_taken'));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function members(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function render(OrganizationContext $context, PlanPolicy $policy): View
    {
        return view('bcm::livewire.plans.form', [
            'types' => array_values(array_filter(PlanType::cases(), fn (PlanType $type): bool => $policy->manages($this->actor(), $type))),
            'people' => $this->members($context),
        ])->title(__($this->planId !== null ? 'bcm::plans.ui.edit' : 'bcm::plans.ui.new'));
    }
}
