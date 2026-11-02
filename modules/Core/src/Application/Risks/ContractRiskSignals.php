<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Risks;

use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Risks\RiskSignal;

/**
 * Contracts that end soon and do not renew themselves: the supplier's service
 * stops with them.
 */
final class ContractRiskSignals
{
    public const int DAYS = 60;

    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        $signals = [];
        $until = today()->addDays(self::DAYS)->toDateString();

        foreach (Contract::query()->with('supplier')->where('auto_renews', false)->whereNotNull('end_date')->whereBetween('end_date', [today()->toDateString(), $until])->limit(500)->get() as $contract) {
            $signals[] = new RiskSignal(
                'core.contract_end:'.$contract->id,
                (string) __('core::risk_signals.source'),
                (string) __('core::risk_signals.contract_end.title', ['supplier' => $contract->supplier->name, 'contract' => $contract->title]),
                (string) __('core::risk_signals.contract_end.description', ['contract' => $contract->title, 'date' => $contract->end_date?->toDateString()]),
                'supplier',
                [['type' => 'supplier', 'id' => $contract->supplier_id]],
                ['Supplier failure', 'Breach of law or contract'],
                ['Dependency on a single supplier'],
                3,
                3,
                route('core.contracts.show', $contract),
            );
        }

        return $signals;
    }
}
