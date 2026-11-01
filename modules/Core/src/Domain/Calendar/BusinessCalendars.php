<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Calendar;

use Modules\Core\Domain\Models\BusinessCalendar;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The working schedule that applies in the current organization.
 */
final readonly class BusinessCalendars
{
    public function __construct(private OrganizationContext $context) {}

    public function forCurrentOrganization(): WorkingSchedule
    {
        if (! $this->context->has()) {
            return WorkingSchedule::default();
        }

        $calendar = BusinessCalendar::query()->where('is_default', true)->with('holidays')->first();

        return $calendar?->toSchedule() ?? WorkingSchedule::default();
    }
}
