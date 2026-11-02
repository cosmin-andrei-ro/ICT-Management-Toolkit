<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Actions;

use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Bcm\Domain\Exceptions\VersionLocked;
use Modules\Bcm\Domain\Models\PlanVersion;

/**
 * Saves the text of an open plan version and the scenarios it covers. A draft
 * may be incomplete; SubmitPlanVersion checks completeness.
 */
final class SavePlanVersion
{
    /**
     * @param  array{objective?: ?string, scenarios?: list<string>, activation_criteria?: ?string, activation_authority?: ?string, communication?: ?string, strategy?: ?string, assumptions?: ?string}  $fields
     *
     * @throws VersionLocked
     */
    public function handle(PlanVersion $version, array $fields): PlanVersion
    {
        if (! $version->status->isEditable()) {
            throw new VersionLocked;
        }

        $scenarios = array_values(array_filter(
            array_map(fn (Scenario $scenario): string => $scenario->value, Scenario::cases()),
            fn (string $value): bool => in_array($value, $fields['scenarios'] ?? [], true),
        ));

        $version->forceFill([
            'objective' => self::blankToNull($fields['objective'] ?? null),
            'scenarios' => $scenarios,
            'activation_criteria' => self::blankToNull($fields['activation_criteria'] ?? null),
            'activation_authority' => self::blankToNull($fields['activation_authority'] ?? null),
            'communication' => self::blankToNull($fields['communication'] ?? null),
            'strategy' => self::blankToNull($fields['strategy'] ?? null),
            'assumptions' => self::blankToNull($fields['assumptions'] ?? null),
        ])->save();

        return $version;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
