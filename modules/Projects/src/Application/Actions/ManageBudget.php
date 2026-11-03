<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Models\Supplier;
use Modules\Projects\Domain\Enums\CostCategory;
use Modules\Projects\Domain\Models\Cost;
use Modules\Projects\Domain\Models\Project;

/**
 * A project's approved budget and the costs recorded against it (ADR 0055).
 */
final readonly class ManageBudget
{
    public function setBudget(Project $project, ?string $amount, string $currency): void
    {
        $currency = strtoupper($currency);

        // Costs already recorded are in the project's currency; it cannot change under them.
        if ($project->currency !== null && $project->currency !== $currency && $project->costs()->exists()) {
            throw ValidationException::withMessages(['currency' => __('projects::budget.errors.currency_locked', ['currency' => $project->currency])]);
        }

        $project->forceFill(['budget_amount' => $amount, 'currency' => $currency])->save();
    }

    /**
     * @param  array{category: CostCategory, description: string, amount: string, incurred_on: string, supplier_id: ?string, invoice_number: ?string}  $data
     */
    public function addCost(Project $project, array $data): Cost
    {
        if ($data['supplier_id'] !== null && ! Supplier::query()->whereKey($data['supplier_id'])->exists()) {
            throw ValidationException::withMessages(['costSupplierId' => __('projects::budget.errors.supplier')]);
        }

        if ($project->currency === null) {
            $project->forceFill(['currency' => config()->string('core.contracts.default_currency')])->save();
        }

        $cost = new Cost;
        $cost->forceFill([...$data, 'project_id' => $project->id])->save();

        return $cost;
    }

    public function deleteCost(Cost $cost): void
    {
        $cost->delete();
    }
}
