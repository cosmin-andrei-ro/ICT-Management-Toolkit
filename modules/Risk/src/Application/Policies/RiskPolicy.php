<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Models\Risk;

/**
 * Owner's decisions: risk managers keep the register; each risk's owner
 * assesses it (scoring, controls, what it concerns); IT and governance staff
 * read the register.
 */
final readonly class RiskPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('risk.risk.view');
    }

    public function view(User $user, Risk $risk): bool
    {
        return $this->inCurrentOrganization($risk) && ($user->can('risk.risk.view') || $risk->owner_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('risk.risk.manage');
    }

    /**
     * Title, category, owner, status.
     */
    public function update(User $user, Risk $risk): bool
    {
        return $this->inCurrentOrganization($risk) && $user->can('risk.risk.manage');
    }

    public function delete(User $user, Risk $risk): bool
    {
        return $this->update($user, $risk);
    }

    /**
     * Scoring, threats and vulnerabilities, controls, assets.
     */
    public function assess(User $user, Risk $risk): bool
    {
        return $this->inCurrentOrganization($risk) && ($risk->owner_id === $user->id || $user->can('risk.risk.manage'));
    }

    private function inCurrentOrganization(Risk $risk): bool
    {
        return $this->context->has() && $risk->organization_id === $this->context->id();
    }
}
