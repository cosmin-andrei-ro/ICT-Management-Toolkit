<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain;

/**
 * Reads an impact matrix (category × time horizon, scores 1–5). The maximum
 * tolerable period of disruption (MTPD) is the first point in time at which any
 * kind of impact reaches the unacceptable level (ISO 22317); before that, the
 * organization can still bear the interruption.
 */
final readonly class ImpactMatrix
{
    /**
     * @param  list<array{id: string, minutes: int}>  $horizons
     * @param  list<string>  $categoryIds
     * @param  array<string, int>  $scores  Keyed "categoryId|horizonId".
     */
    public function __construct(
        private array $horizons,
        private array $categoryIds,
        private array $scores,
    ) {}

    /**
     * Minutes, or null when no score reaches the level within the horizons scored.
     */
    public function mtpd(int $unacceptableLevel): ?int
    {
        $horizons = $this->horizons;
        usort($horizons, fn (array $a, array $b): int => $a['minutes'] <=> $b['minutes']);

        foreach ($horizons as $horizon) {
            foreach ($this->categoryIds as $categoryId) {
                if (($this->scores[$categoryId.'|'.$horizon['id']] ?? 0) >= $unacceptableLevel) {
                    return $horizon['minutes'];
                }
            }
        }

        return null;
    }

    /**
     * Every cell scored.
     */
    public function isComplete(): bool
    {
        foreach ($this->horizons as $horizon) {
            foreach ($this->categoryIds as $categoryId) {
                if (! isset($this->scores[$categoryId.'|'.$horizon['id']])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Impact must not shrink with time: a later horizon scores at least as high.
     *
     * @return list<string> Categories whose scores go down.
     */
    public function decreasingCategories(): array
    {
        $horizons = $this->horizons;
        usort($horizons, fn (array $a, array $b): int => $a['minutes'] <=> $b['minutes']);
        $decreasing = [];

        foreach ($this->categoryIds as $categoryId) {
            $previous = 0;

            foreach ($horizons as $horizon) {
                $score = $this->scores[$categoryId.'|'.$horizon['id']] ?? null;

                if ($score !== null && $score < $previous) {
                    $decreasing[] = $categoryId;

                    break;
                }

                $previous = $score ?? $previous;
            }
        }

        return $decreasing;
    }
}
