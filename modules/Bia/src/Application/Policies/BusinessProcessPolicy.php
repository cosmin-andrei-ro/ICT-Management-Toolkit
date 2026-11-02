<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Policies;

use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The continuity coordinator keeps the list of processes (bia.process.manage); people
 * with bia.process.view read it. A process's owner and deputy see their own
 * process and fill in its assessments, whatever their role (owner's decision).
 */
final readonly class BusinessProcessPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('bia.process.view');
    }

    public function view(User $user, BusinessProcess $process): bool
    {
        return $this->inCurrentOrganization($process) && ($user->can('bia.process.view') || $process->isOwnedBy($user));
    }

    public function create(User $user): bool
    {
        return $user->can('bia.process.manage');
    }

    public function update(User $user, BusinessProcess $process): bool
    {
        return $this->inCurrentOrganization($process) && $user->can('bia.process.manage');
    }

    public function delete(User $user, BusinessProcess $process): bool
    {
        return $this->inCurrentOrganization($process) && $user->can('bia.process.manage');
    }

    /**
     * Start or continue an assessment of an active process.
     */
    public function assess(User $user, BusinessProcess $process): bool
    {
        return $this->inCurrentOrganization($process)
            && $process->status === 'active'
            && ($process->isOwnedBy($user) || $user->can('bia.process.manage'));
    }

    private function inCurrentOrganization(BusinessProcess $process): bool
    {
        return $this->context->has() && $process->organization_id === $this->context->id();
    }
}
