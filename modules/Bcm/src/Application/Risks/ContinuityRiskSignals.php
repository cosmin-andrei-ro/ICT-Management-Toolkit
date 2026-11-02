<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Risks;

use Carbon\CarbonImmutable;
use Modules\Bcm\Application\Coverage\ContinuityCoverage;
use Modules\Core\Domain\Risks\RiskSignal;

/**
 * Continuity risks the plans reveal: assessed processes without a plan in
 * force, and plans that would not bring the business back within its RTO.
 */
final readonly class ContinuityRiskSignals
{
    public function __construct(private ContinuityCoverage $coverage) {}

    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        $signals = [];
        $source = (string) __('bcm::risk_signals.source');
        $plans = $this->coverage->plans(CarbonImmutable::today());

        foreach ($this->coverage->processes($plans) as $row) {
            if ($row->isCovered()) {
                continue;
            }

            $signals[] = new RiskSignal(
                'bcm.no_plan:'.$row->process->id, $source,
                (string) __('bcm::risk_signals.no_plan.title', ['process' => $row->process->name]),
                (string) __('bcm::risk_signals.no_plan.description', ['process' => $row->process->name]),
                'availability', [['type' => 'process', 'id' => $row->process->id]],
                [], ['Undocumented procedures', 'Single point of failure'],
                null, $row->process->tier !== null && $row->process->tier <= 2 ? 4 : 3, route('bcm.coverage'),
            );
        }

        foreach ($plans as $standing) {
            if ($standing->fitsRto() !== false) {
                continue;
            }

            $signals[] = new RiskSignal(
                'bcm.too_slow:'.$standing->plan->id, $source,
                (string) __('bcm::risk_signals.too_slow.title', ['plan' => $standing->plan->name]),
                (string) __('bcm::risk_signals.too_slow.description', ['plan' => $standing->plan->name]),
                'availability', [],
                [], ['Single point of failure'],
                null, 4, route('bcm.plans.show', $standing->plan), $standing->plan->owner_id,
            );
        }

        return $signals;
    }
}
