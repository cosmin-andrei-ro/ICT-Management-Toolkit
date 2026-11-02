<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Policies;

use Illuminate\Contracts\Auth\Access\Gate;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Assessments follow their process; only open ones change. Approval is a
 * separate duty: whoever owns the process or submitted the version cannot
 * approve it, whatever their permissions.
 */
final readonly class AssessmentPolicy
{
    public function __construct(
        private OrganizationContext $context,
        private Gate $gate,
    ) {}

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->inCurrentOrganization($assessment) && $this->gate->forUser($user)->allows('view', $assessment->process);
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $this->inCurrentOrganization($assessment)
            && $assessment->status->isEditable()
            && $this->gate->forUser($user)->allows('assess', $assessment->process);
    }

    public function decide(User $user, Assessment $assessment): bool
    {
        return $this->inCurrentOrganization($assessment)
            && $assessment->status === AssessmentStatus::Submitted
            && $user->can('bia.assessment.approve')
            && ! $assessment->process->isOwnedBy($user)
            && $assessment->submitted_by !== $user->id;
    }

    private function inCurrentOrganization(Assessment $assessment): bool
    {
        return $this->context->has() && $assessment->organization_id === $this->context->id();
    }
}
