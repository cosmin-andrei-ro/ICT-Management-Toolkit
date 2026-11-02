<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Policies;

use Modules\Audit\Domain\Models\Audit;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Owner's decisions: compliance managers plan and run audits; auditors and
 * governance staff read them.
 */
final readonly class AuditPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('audit.compliance.view');
    }

    public function view(User $user, Audit $audit): bool
    {
        return $this->inCurrentOrganization($audit) && $user->can('audit.compliance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('audit.compliance.manage');
    }

    /**
     * Details, status, findings.
     */
    public function update(User $user, Audit $audit): bool
    {
        return $this->inCurrentOrganization($audit) && $user->can('audit.compliance.manage');
    }

    public function delete(User $user, Audit $audit): bool
    {
        return $this->update($user, $audit);
    }

    private function inCurrentOrganization(Audit $audit): bool
    {
        return $this->context->has() && $audit->organization_id === $this->context->id();
    }
}
