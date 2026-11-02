<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Risk\Domain\Enums\RiskLevel;
use Modules\Risk\Domain\Models\RiskSettings;

it('places a score in the organization\'s bands', function (): void {
    $settings = new RiskSettings;
    $settings->forceFill(['medium_from' => 5, 'high_from' => 10, 'critical_from' => 17]);

    expect(array_map(fn (int $score): RiskLevel => $settings->level($score), [1, 4, 5, 9, 10, 16, 17, 25]))
        ->toBe([RiskLevel::Low, RiskLevel::Low, RiskLevel::Medium, RiskLevel::Medium, RiskLevel::High, RiskLevel::High, RiskLevel::Critical, RiskLevel::Critical]);
});
