<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Policies;

use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Owner's decisions: compliance managers raise findings; each finding's
 * owner works it (root cause, corrective actions) and submits it; its
 * closure is verified by a compliance manager other than its owner and
 * whoever submitted it. Owners of corrective actions report their progress.
 */
final readonly class FindingPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('audit.compliance.view');
    }

    public function view(User $user, Finding $finding): bool
    {
        return $this->inCurrentOrganization($finding)
            && ($user->can('audit.compliance.view') || $finding->owner_id === $user->id || $finding->actions()->where('owner_id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->can('audit.compliance.manage');
    }

    /**
     * Title, description, severity, owner, due date, what it concerns.
     */
    public function update(User $user, Finding $finding): bool
    {
        return $this->inCurrentOrganization($finding) && $finding->status !== FindingStatus::Closed && $user->can('audit.compliance.manage');
    }

    /**
     * Root cause, corrective actions, submitting for verification.
     */
    public function work(User $user, Finding $finding): bool
    {
        return $this->inCurrentOrganization($finding) && $finding->status === FindingStatus::Open
            && ($finding->owner_id === $user->id || $user->can('audit.compliance.manage'));
    }

    public function progressAction(User $user, Finding $finding, FindingAction $action): bool
    {
        return $action->finding_id === $finding->id && $finding->status === FindingStatus::Open
            && ($action->owner_id === $user->id || $this->work($user, $finding));
    }

    public function verify(User $user, Finding $finding): bool
    {
        return $this->inCurrentOrganization($finding) && $finding->status === FindingStatus::Verification
            && $user->can('audit.compliance.manage')
            && $finding->owner_id !== $user->id && $finding->submitted_by !== $user->id;
    }

    private function inCurrentOrganization(Finding $finding): bool
    {
        return $this->context->has() && $finding->organization_id === $this->context->id();
    }
}
