<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application;

use Modules\Projects\Domain\Enums\CostCategory;
use Modules\Projects\Domain\Models\Cost;
use Modules\Projects\Domain\Models\Project;

/**
 * Budget, spent, remaining and the share used, with the spending by category.
 */
final readonly class BudgetFigures
{
    /**
     * @param  array<string, float>  $byCategory
     */
    private function __construct(
        public ?float $budget,
        public float $spent,
        public ?float $remaining,
        public ?int $percent,
        public array $byCategory,
        public ?string $currency,
    ) {}

    public static function of(Project $project): self
    {
        $byCategory = array_fill_keys(array_map(fn (CostCategory $category): string => $category->value, CostCategory::cases()), 0.0);

        foreach ($project->costs()->get(['category', 'amount']) as $cost) {
            /** @var Cost $cost */
            $byCategory[$cost->category->value] += (float) $cost->amount;
        }

        $spent = array_sum($byCategory);
        $budget = $project->budget_amount !== null ? (float) $project->budget_amount : null;

        return new self(
            $budget,
            $spent,
            $budget !== null ? $budget - $spent : null,
            $budget !== null && $budget > 0 ? (int) round($spent / $budget * 100) : null,
            $byCategory,
            $project->currency,
        );
    }

    public function isOver(): bool
    {
        return $this->budget !== null && $this->spent > $this->budget;
    }
}
