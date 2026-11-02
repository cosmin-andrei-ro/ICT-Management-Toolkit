<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Risks;

use Modules\Bia\Application\Gaps\GapAnalysis;
use Modules\Bia\Application\Gaps\GapRow;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Enums\DependencyKind;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\Dependency;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Core\Domain\Time\Duration;

/**
 * Continuity risks the BIA reveals: systems IT cannot bring back as fast as
 * the business needs (recovery gaps), and critical processes (tier 1–2) that
 * depend on a supplier.
 */
final readonly class BiaRiskSignals
{
    public function __construct(private GapAnalysis $gaps) {}

    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        $signals = [];
        $source = (string) __('bia::risk_signals.source');

        foreach ($this->gaps->rows() as $row) {
            if ($row->status() !== GapRow::GAP) {
                continue;
            }

            $replace = ['item' => $row->item->name, 'rto' => Duration::format($row->requiredRto), 'processes' => implode(', ', array_map(fn (array $process): string => $process['name'], $row->processes))];
            $signals[] = new RiskSignal(
                'bia.recovery_gap:'.$row->item->id, $source,
                (string) __('bia::risk_signals.gap.title', $replace),
                (string) __('bia::risk_signals.gap.description', $replace),
                'availability', [['type' => 'configuration_item', 'id' => $row->item->id]],
                ['Hardware failure', 'Software failure'], ['Single point of failure', 'No tested backup'],
                null, 4, route('bia.gaps'),
            );
        }

        $approved = Assessment::query()->with('process')->where('status', AssessmentStatus::Approved->value)->get()
            ->filter(fn (Assessment $assessment): bool => $assessment->process->status === 'active' && $assessment->process->tier !== null && $assessment->process->tier->value <= 2)
            ->keyBy('id');

        foreach (Dependency::query()->with('supplier')->where('kind', DependencyKind::Supplier->value)->whereIn('assessment_id', $approved->keys()->all())->get() as $dependency) {
            $process = $approved->get($dependency->assessment_id)?->process;

            if ($process === null || $dependency->supplier === null) {
                continue;
            }

            $replace = ['supplier' => $dependency->supplier->name, 'process' => $process->name];
            $signals[] = new RiskSignal(
                'bia.supplier:'.$process->id.':'.$dependency->supplier->id, $source,
                (string) __('bia::risk_signals.supplier.title', $replace),
                (string) __('bia::risk_signals.supplier.description', $replace),
                'supplier', [['type' => 'process', 'id' => $process->id], ['type' => 'supplier', 'id' => $dependency->supplier->id]],
                ['Supplier failure'], ['Dependency on a single supplier'],
                null, 4, route('bia.processes.show', $process), $process->owner_id,
            );
        }

        return $signals;
    }
}
