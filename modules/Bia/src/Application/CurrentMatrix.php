<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application;

use Illuminate\Support\Collection;
use Modules\Bia\Domain\ImpactMatrix;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;

/**
 * The organization's active impact categories and time horizons, in display
 * order, and a matrix of scores read against them.
 */
final readonly class CurrentMatrix
{
    /**
     * @param  Collection<int, ImpactCategory>  $categories
     * @param  Collection<int, TimeHorizon>  $horizons
     */
    public function __construct(
        public Collection $categories,
        public Collection $horizons,
    ) {}

    public static function load(): self
    {
        return new self(
            ImpactCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            TimeHorizon::query()->where('is_active', true)->orderBy('minutes')->get(),
        );
    }

    /**
     * @param  array<string, int>  $scores  Keyed "categoryId|horizonId".
     */
    public function read(array $scores): ImpactMatrix
    {
        return new ImpactMatrix(
            array_values($this->horizons->map(fn (TimeHorizon $horizon): array => ['id' => $horizon->id, 'minutes' => $horizon->minutes])->all()),
            array_values($this->categories->map(fn (ImpactCategory $category): string => $category->id)->all()),
            $scores,
        );
    }

    /**
     * Only cells of active categories and horizons, with scores 1–5.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, int>
     */
    public function clean(array $input): array
    {
        $scores = [];

        foreach ($this->categories as $category) {
            foreach ($this->horizons as $horizon) {
                $value = $input[$category->id.'|'.$horizon->id] ?? null;

                if (is_numeric($value) && (int) $value >= 1 && (int) $value <= 5) {
                    $scores[$category->id.'|'.$horizon->id] = (int) $value;
                }
            }
        }

        return $scores;
    }
}
