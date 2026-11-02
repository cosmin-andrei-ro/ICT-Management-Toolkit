<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Enums\RequirementState;

it('derives where a requirement stands from its applicability and its controls', function (?bool $applicable, array $controls, RequirementState $expected): void {
    expect(RequirementState::of($applicable, $controls))->toBe($expected);
})->with([
    'undecided, no control' => [null, [], RequirementState::Undecided],
    'not applicable, whatever the controls' => [false, [ControlStatus::Implemented], RequirementState::NotApplicable],
    'applicable, no control' => [true, [], RequirementState::NotCovered],
    'only retired controls' => [true, [ControlStatus::Retired], RequirementState::NotCovered],
    'planned' => [true, [ControlStatus::Planned], RequirementState::Planned],
    'one implemented, one planned' => [true, [ControlStatus::Implemented, ControlStatus::Planned], RequirementState::Partial],
    'partial' => [null, [ControlStatus::Partial], RequirementState::Partial],
    'all implemented, retired ignored' => [true, [ControlStatus::Implemented, ControlStatus::Retired], RequirementState::Implemented],
]);
