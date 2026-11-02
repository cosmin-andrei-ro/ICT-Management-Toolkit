<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Risks;

use Modules\Audit\Application\Reminders\SendComplianceDigest;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Framework;
use Modules\Core\Domain\Risks\RiskSignal;

/**
 * Compliance risks: major nonconformities not yet closed, and frameworks
 * with applicable requirements no control meets.
 */
final readonly class ComplianceRiskSignals
{
    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        $signals = [];
        $source = (string) __('audit::report.risk_signals.source');

        foreach (Finding::query()->where('severity', FindingSeverity::MajorNonconformity->value)->where('status', '!=', FindingStatus::Closed->value)->orderBy('number')->get() as $finding) {
            $signals[] = new RiskSignal(
                'audit.major:'.$finding->id, $source,
                (string) __('audit::report.risk_signals.major.title', ['finding' => $finding->title]),
                (string) __('audit::report.risk_signals.major.description', ['finding' => $finding->code().' '.$finding->title]),
                'compliance', [],
                ['Breach of law or contract'], [],
                null, 4, route('audit.findings.show', $finding), $finding->owner_id,
            );
        }

        $uncovered = SendComplianceDigest::uncovered()->toBase()->selectRaw('framework_id, count(*) as total')->groupBy('framework_id')->pluck('total', 'framework_id');

        foreach (Framework::query()->whereKey($uncovered->keys()->all())->orderBy('name')->get() as $framework) {
            $count = $uncovered->get($framework->id);
            $signals[] = new RiskSignal(
                'audit.uncovered:'.$framework->id, $source,
                (string) __('audit::report.risk_signals.uncovered.title', ['framework' => $framework->name]),
                (string) trans_choice('audit::report.risk_signals.uncovered.description', is_numeric($count) ? (int) $count : 0, ['count' => is_numeric($count) ? (int) $count : 0, 'framework' => $framework->name]),
                'compliance', [],
                ['Breach of law or contract'], [],
                null, null, route('audit.frameworks.show', ['framework' => $framework, 'state' => 'not_covered']),
            );
        }

        return $signals;
    }
}
