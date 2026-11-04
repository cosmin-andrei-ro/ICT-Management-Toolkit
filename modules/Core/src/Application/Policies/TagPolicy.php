<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\Tag;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Tags are for IT staff: a name such as "breach-2026" says something by itself.
 */
final readonly class TagPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.tag.view');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->inCurrentOrganization($tag) && $user->can('core.tag.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $this->inCurrentOrganization($tag) && $user->can('core.tag.manage');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->update($user, $tag);
    }

    private function inCurrentOrganization(Tag $tag): bool
    {
        return $tag->organization_id === $this->context->id();
    }
}
