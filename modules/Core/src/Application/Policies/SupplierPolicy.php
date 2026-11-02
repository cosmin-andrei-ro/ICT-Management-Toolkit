<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * IT staff see suppliers and their support contacts; contract managers keep them up to date.
 */
final readonly class SupplierPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->inCurrentOrganization($supplier) && $user->can('core.supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->can('core.supplier.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->inCurrentOrganization($supplier) && $user->can('core.supplier.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->inCurrentOrganization($supplier) && $user->can('core.supplier.manage');
    }

    private function inCurrentOrganization(Supplier $supplier): bool
    {
        return $this->context->has() && $supplier->organization_id === $this->context->id();
    }
}
