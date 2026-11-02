<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Links;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Links\Risks;
use Modules\Core\Domain\Links\RiskSummary;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * The Risk side of Core's Risks contract.
 */
final readonly class RegisterRisks implements Risks
{
    public function __construct(private ConnectionInterface $db) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function concerning(string $assetType, string $assetId, User $viewer): array
    {
        $ids = $this->db->table('risk_risk_assets')->where('asset_type', $assetType)->where('asset_id', $assetId)->pluck('risk_id')->all();
        $settings = RiskSettings::current();

        return array_values(Risk::query()->whereKey($ids)->where('status', 'open')->limit(50)->get()
            ->filter(fn (Risk $risk): bool => $viewer->can('view', $risk))
            ->sortByDesc(fn (Risk $risk): int => $risk->currentScore() ?? 0)
            ->map(fn (Risk $risk): RiskSummary => new RiskSummary(
                $risk->id,
                $risk->code(),
                $risk->title,
                route('risk.risks.show', $risk),
                $risk->currentScore() !== null ? $settings->level((int) $risk->currentScore())->label() : null,
                $risk->currentScore(),
                $risk->status->label(),
            ))
            ->all());
    }
}
