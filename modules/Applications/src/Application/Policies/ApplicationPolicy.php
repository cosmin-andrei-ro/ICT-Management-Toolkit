<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Policies;

use Modules\Applications\Domain\Models\Application;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Owner's decisions: application managers keep the portfolio; each
 * application's business owner, technical owner and deputy keep its sheet up
 * to date; IT staff read.
 */
final readonly class ApplicationPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('applications.application.view');
    }

    public function view(User $user, Application $application): bool
    {
        return $this->inCurrentOrganization($application) && ($user->can('applications.application.view') || $application->isOwnedBy($user));
    }

    public function create(User $user): bool
    {
        return $user->can('applications.application.manage');
    }

    /**
     * Identity and owners.
     */
    public function update(User $user, Application $application): bool
    {
        return $this->inCurrentOrganization($application) && $user->can('applications.application.manage');
    }

    public function delete(User $user, Application $application): bool
    {
        return $this->update($user, $application);
    }

    /**
     * Start or write a version of the sheet.
     */
    public function edit(User $user, Application $application): bool
    {
        return $this->inCurrentOrganization($application) && ($application->isOwnedBy($user) || $user->can('applications.application.manage'));
    }

    private function inCurrentOrganization(Application $application): bool
    {
        return $this->context->has() && $application->organization_id === $this->context->id();
    }
}
