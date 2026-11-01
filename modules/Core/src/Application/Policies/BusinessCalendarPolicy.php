<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\BusinessCalendar;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

final readonly class BusinessCalendarPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.calendar.manage');
    }

    public function view(User $user, BusinessCalendar $calendar): bool
    {
        return $this->inCurrentOrganization($calendar) && $user->can('core.calendar.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('core.calendar.manage');
    }

    public function update(User $user, BusinessCalendar $calendar): bool
    {
        return $this->inCurrentOrganization($calendar) && $user->can('core.calendar.manage');
    }

    public function delete(User $user, BusinessCalendar $calendar): bool
    {
        return $this->inCurrentOrganization($calendar) && $user->can('core.calendar.manage');
    }

    private function inCurrentOrganization(BusinessCalendar $calendar): bool
    {
        return $this->context->has() && $calendar->organization_id === $this->context->id();
    }
}
