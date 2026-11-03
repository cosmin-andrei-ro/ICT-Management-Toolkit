<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\Actions\ManageBudget;
use Modules\Projects\Application\BudgetFigures;
use Modules\Projects\Domain\Enums\CostCategory;
use Modules\Projects\Domain\Models\Cost;
use Modules\Projects\Domain\Models\Project;

/**
 * On a project's page: the approved budget, the costs, and what is left.
 */
final class ProjectBudget extends Component
{
    #[Locked]
    public string $projectId = '';

    public bool $editingBudget = false;

    public string $budgetAmount = '';

    public string $currency = '';

    public string $costCategory = 'external_labour';

    public string $costDescription = '';

    public string $costAmount = '';

    public string $costIncurredOn = '';

    public string $costSupplierId = '';

    public string $costInvoice = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->projectId = $project->id;
        $this->costIncurredOn = today()->toDateString();
    }

    public function editBudget(): void
    {
        $project = $this->project();
        $this->authorize('update', $project);
        $this->editingBudget = true;
        $this->budgetAmount = $project->budget_amount ?? '';
        $this->currency = $project->currency ?? config()->string('core.contracts.default_currency');
    }

    public function saveBudget(ManageBudget $budget): void
    {
        $project = $this->project();
        $this->authorize('update', $project);
        $this->resetErrorBag();
        $this->validate([
            'budgetAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ]);
        $budget->setBudget($project, $this->budgetAmount !== '' ? $this->budgetAmount : null, $this->currency);
        $this->editingBudget = false;
    }

    public function addCost(ManageBudget $budget): void
    {
        $project = $this->project();
        $this->authorize('update', $project);
        $this->resetErrorBag();
        $this->validate([
            'costCategory' => ['required', Rule::enum(CostCategory::class)],
            'costDescription' => ['required', 'string', 'max:255'],
            'costAmount' => ['required', 'numeric', 'not_in:0', 'min:-999999999999', 'max:999999999999'],
            'costIncurredOn' => ['required', 'date'],
            'costSupplierId' => ['nullable', 'uuid'],
            'costInvoice' => ['nullable', 'string', 'max:100'],
        ]);

        $budget->addCost($project, [
            'category' => CostCategory::from($this->costCategory),
            'description' => trim($this->costDescription),
            'amount' => $this->costAmount,
            'incurred_on' => $this->costIncurredOn,
            'supplier_id' => $this->costSupplierId !== '' ? $this->costSupplierId : null,
            'invoice_number' => trim($this->costInvoice) !== '' ? trim($this->costInvoice) : null,
        ]);
        $this->reset('costDescription', 'costAmount', 'costSupplierId', 'costInvoice');
    }

    public function deleteCost(string $costId, ManageBudget $budget): void
    {
        $this->authorize('update', $this->project());
        $budget->deleteCost(Cost::query()->where('project_id', $this->projectId)->findOrFail($costId));
    }

    public function render(): View
    {
        $project = $this->project();
        $user = auth()->user();

        return view('projects::livewire.budget', [
            'project' => $project,
            'figures' => BudgetFigures::of($project),
            'costs' => $project->costs()->with('supplier')->orderByDesc('incurred_on')->limit(100)->get(),
            'categories' => CostCategory::cases(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => $user instanceof User && $user->can('update', $project),
        ]);
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }
}
