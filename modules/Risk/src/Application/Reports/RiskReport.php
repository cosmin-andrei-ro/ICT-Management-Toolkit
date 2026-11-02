<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Reports;

use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\RiskAssets;
use Modules\Risk\Domain\Enums\RiskLevel;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * The risk report for management: the method, the heat maps before and after
 * treatment, the risks by level, and each open risk with its treatment.
 */
final readonly class RiskReport
{
    public function __construct(
        private RiskAssets $assets,
        private OrganizationContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $settings = RiskSettings::current();
        $risks = Risk::query()->with(['owner', 'catalogEntries', 'actions.owner'])->where('status', 'open')->get()
            ->sortByDesc(fn (Risk $risk): int => $risk->currentScore() ?? 0)->values();
        $heat = ['inherent' => [], 'current' => []];
        $byLevel = ['inherent' => [], 'current' => []];

        foreach ($risks as $risk) {
            if ($risk->inherent_likelihood === null || $risk->inherent_impact === null) {
                continue;
            }

            $current = [$risk->residual_likelihood ?? $risk->inherent_likelihood, $risk->residual_impact ?? $risk->inherent_impact];

            foreach (['inherent' => [$risk->inherent_likelihood, $risk->inherent_impact], 'current' => $current] as $map => [$likelihood, $impact]) {
                $heat[$map][$likelihood][$impact] = ($heat[$map][$likelihood][$impact] ?? 0) + 1;
                $level = $settings->level($likelihood * $impact)->value;
                $byLevel[$map][$level] = ($byLevel[$map][$level] ?? 0) + 1;
            }
        }

        return [
            'organization' => Organization::query()->find($this->context->requireId()),
            'settings' => $settings,
            'risks' => $risks,
            'assets' => $risks->mapWithKeys(fn (Risk $risk): array => [$risk->id => $this->assets->resolve($risk->assets())])->all(),
            'heat' => $heat,
            'byLevel' => $byLevel,
            'levels' => RiskLevel::cases(),
            'unscored' => $risks->filter(fn (Risk $risk): bool => $risk->inherentScore() === null)->count(),
        ];
    }
}
