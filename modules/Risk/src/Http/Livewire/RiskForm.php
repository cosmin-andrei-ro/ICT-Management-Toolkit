<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Actions\SaveRisk;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\RiskStatus;
use Modules\Risk\Domain\Models\Risk;

/**
 * Identifies a risk: the scenario, its category and its owner.
 */
#[Layout('core::components.layouts.app')]
final class RiskForm extends Component
{
    #[Locked]
    public ?string $riskId = null;

    public string $title = '';

    public string $description = '';

    public string $category = 'information_security';

    public string $ownerId = '';

    public string $status = 'open';

    public function mount(?Risk $risk = null): void
    {
        if ($risk === null || ! $risk->exists) {
            $this->authorize('create', Risk::class);

            return;
        }

        $this->authorize('update', $risk);
        $this->riskId = $risk->id;
        $this->title = $risk->title;
        $this->description = $risk->description ?? '';
        $this->category = $risk->category->value;
        $this->ownerId = $risk->owner_id ?? '';
        $this->status = $risk->status->value;
    }

    public function save(SaveRisk $save, OrganizationContext $context): void
    {
        $risk = $this->riskId !== null ? Risk::query()->findOrFail($this->riskId) : null;
        $this->authorize($risk !== null ? 'update' : 'create', $risk ?? Risk::class);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', Rule::enum(RiskCategory::class)],
            'ownerId' => ['required', 'uuid', Rule::in($this->people($context)->pluck('id')->all())],
            'status' => ['required', Rule::enum(RiskStatus::class)],
        ]);

        $saved = $save->handle($risk, [
            'title' => $this->title,
            'description' => $this->description,
            'category' => RiskCategory::from($this->category),
            'owner_id' => $this->ownerId,
            'status' => RiskStatus::from($this->status),
        ]);

        $this->redirectRoute('risk.risks.show', $saved);
    }

    public function render(OrganizationContext $context): View
    {
        return view('risk::livewire.risks.form', [
            'people' => $this->people($context),
            'categories' => RiskCategory::cases(),
            'statuses' => RiskStatus::cases(),
        ])->title(__($this->riskId !== null ? 'risk::risks.ui.edit' : 'risk::risks.ui.new'));
    }

    /**
     * @return Collection<int, User>
     */
    private function people(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }
}
