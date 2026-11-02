<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\Category;

/**
 * Asset categories and their attributes: configuration, for itam.category.manage.
 */
final readonly class CategoryPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('itam.category.manage');
    }

    public function view(User $user, Category $category): bool
    {
        return $this->inCurrentOrganization($category) && $user->can('itam.category.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('itam.category.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->inCurrentOrganization($category) && $user->can('itam.category.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->inCurrentOrganization($category) && $user->can('itam.category.manage');
    }

    private function inCurrentOrganization(Category $category): bool
    {
        return $this->context->has() && $category->organization_id === $this->context->id();
    }
}
