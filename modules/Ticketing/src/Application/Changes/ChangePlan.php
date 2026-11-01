<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Ticketing\Domain\Changes\ChangeRisk;

/**
 * What will be done, how to undo it, how to verify it, and when.
 */
final readonly class ChangePlan
{
    public const int MAX_LENGTH = 20_000;

    public ?CarbonImmutable $plannedStart;

    public ?CarbonImmutable $plannedEnd;

    public function __construct(
        public ChangeRisk $risk,
        public string $implementationPlan,
        public string $rollbackPlan,
        public string $testPlan,
        ?CarbonImmutable $plannedStart,
        ?CarbonImmutable $plannedEnd,
    ) {
        // Eloquent writes timestamps without an offset: store the window in the application time zone.
        $timezone = config('app.timezone');
        $timezone = is_string($timezone) ? $timezone : 'UTC';
        $this->plannedStart = $plannedStart?->setTimezone($timezone);
        $this->plannedEnd = $plannedEnd?->setTimezone($timezone);

        foreach ([$implementationPlan, $rollbackPlan, $testPlan] as $text) {
            if (mb_strlen($text) > self::MAX_LENGTH) {
                throw new InvalidArgumentException('A change plan is limited to '.self::MAX_LENGTH.' characters.');
            }
        }

        if ($plannedStart !== null && $plannedEnd !== null && $plannedEnd->lte($plannedStart)) {
            throw new InvalidArgumentException('The window must end after it starts.');
        }
    }
}
