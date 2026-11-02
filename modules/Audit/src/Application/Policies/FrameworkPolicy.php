<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Policies;

use Modules\Audit\Domain\Models\Framework;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Owner's decisions: compliance managers keep the frameworks and the
 * applicability of their requirements; auditors and governance staff read.
 */
final readonly class FrameworkPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('audit.compliance.view');
    }

    public function view(User $user, Framework $framework): bool
    {
        return $this->inCurrentOrganization($framework) && $user->can('audit.compliance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('audit.compliance.manage');
    }

    /**
     * Requirements, their applicability, archiving.
     */
    public function update(User $user, Framework $framework): bool
    {
        return $this->inCurrentOrganization($framework) && $user->can('audit.compliance.manage');
    }

    private function inCurrentOrganization(Framework $framework): bool
    {
        return $this->context->has() && $framework->organization_id === $this->context->id();
    }
}
