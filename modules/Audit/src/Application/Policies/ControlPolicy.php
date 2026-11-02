<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Policies;

use Modules\Audit\Domain\Models\Control;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Owner's decisions: compliance managers keep the controls and what they
 * meet; each control's owner says how far it is implemented; auditors and
 * governance staff read.
 */
final readonly class ControlPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('audit.compliance.view');
    }

    public function view(User $user, Control $control): bool
    {
        return $this->inCurrentOrganization($control) && ($user->can('audit.compliance.view') || $control->owner_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('audit.compliance.manage');
    }

    /**
     * Name, description, owner, the requirements it meets.
     */
    public function update(User $user, Control $control): bool
    {
        return $this->inCurrentOrganization($control) && $user->can('audit.compliance.manage');
    }

    public function delete(User $user, Control $control): bool
    {
        return $this->update($user, $control);
    }

    /**
     * Status and implementation notes.
     */
    public function implement(User $user, Control $control): bool
    {
        return $this->inCurrentOrganization($control) && ($control->owner_id === $user->id || $user->can('audit.compliance.manage'));
    }

    private function inCurrentOrganization(Control $control): bool
    {
        return $this->context->has() && $control->organization_id === $this->context->id();
    }
}
