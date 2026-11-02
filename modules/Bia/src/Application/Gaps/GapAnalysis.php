<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Gaps;

use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Enums\Tier;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Links\ConfigurationItems;

/**
 * What the business requires of each configuration item against what IT can
 * deliver. Every active process with an approved assessment passes its RTO and
 * RPO to the items it uses and, through the CMDB's relationships, to everything
 * those items need. Each item gets the strictest requirement.
 */
final readonly class GapAnalysis
{
    public function __construct(private ConfigurationItems $items) {}

    /**
     * @return list<GapRow> Gaps first.
     */
    public function rows(): array
    {
        if (! $this->items->isAvailable()) {
            return [];
        }

        $processes = BusinessProcess::query()->where('status', 'active')->whereNotNull('rto_minutes')->get()->keyBy('id');
        $assessments = Assessment::query()->where('status', AssessmentStatus::Approved->value)->whereIn('process_id', $processes->keys()->all())->get(['id', 'process_id']);
        $linked = $this->items->linkedIds((new Assessment)->getMorphClass(), array_values($assessments->map(fn (Assessment $assessment): string => $assessment->id)->all()));

        /** @var array<string, array{rto: int, rpo: ?int, tier: Tier, processes: list<array{name: string, url: string, rto: int, rpo: ?int, direct: bool}>}> $requirements */
        $requirements = [];

        foreach ($assessments as $assessment) {
            $process = $processes->get($assessment->process_id);
            $direct = $linked[$assessment->id] ?? [];

            if ($process === null || $process->rto_minutes === null || $direct === []) {
                continue;
            }

            $tier = Tier::fromRto($process->rto_minutes);
            $indirect = $this->items->dependencyIdsOf($direct);

            foreach ([...array_map(fn (string $id): array => [$id, true], $direct), ...array_map(fn (string $id): array => [$id, false], $indirect)] as [$itemId, $isDirect]) {
                $current = $requirements[$itemId] ?? ['rto' => PHP_INT_MAX, 'rpo' => null, 'tier' => Tier::Four, 'processes' => []];
                $current['rto'] = min($current['rto'], $process->rto_minutes);
                $current['rpo'] = $process->rpo_minutes === null ? $current['rpo'] : min($current['rpo'] ?? PHP_INT_MAX, $process->rpo_minutes);
                $current['tier'] = $tier->value < $current['tier']->value ? $tier : $current['tier'];
                $current['processes'][] = ['name' => $process->name, 'url' => route('bia.processes.show', $process), 'rto' => $process->rto_minutes, 'rpo' => $process->rpo_minutes, 'direct' => $isDirect];
                $requirements[$itemId] = $current;
            }
        }

        $profiles = $this->items->recoveryProfiles(array_keys($requirements));
        $rows = [];

        foreach ($requirements as $itemId => $requirement) {
            $profile = $profiles[$itemId] ?? null;

            if ($profile === null) {
                continue;
            }

            $rows[] = new GapRow($profile, $requirement['rto'], $requirement['rpo'], $requirement['processes'], self::criticalityFor($requirement['tier']));
        }

        usort($rows, fn (GapRow $a, GapRow $b): int => strcmp($a->sortKey(), $b->sortKey()));

        return $rows;
    }

    /**
     * Tier 1 items are critical, tier 2 high, tier 3 medium, tier 4 low.
     */
    public static function criticalityFor(Tier $tier): string
    {
        return match ($tier) {
            Tier::One => 'critical',
            Tier::Two => 'high',
            Tier::Three => 'medium',
            Tier::Four => 'low',
        };
    }
}
