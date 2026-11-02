<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Sso\SsoGroupMapping;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Identity providers and their group mappings decide who signs in and with
 * which roles: as sensitive as the AD group mappings, same permission.
 */
final readonly class SsoPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.directory.manage');
    }

    public function view(User $user, SsoProvider|SsoGroupMapping $record): bool
    {
        return $this->inCurrentOrganization($record) && $user->can('core.directory.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('core.directory.manage');
    }

    public function update(User $user, SsoProvider|SsoGroupMapping $record): bool
    {
        return $this->inCurrentOrganization($record) && $user->can('core.directory.manage');
    }

    public function delete(User $user, SsoProvider|SsoGroupMapping $record): bool
    {
        return $this->inCurrentOrganization($record) && $user->can('core.directory.manage');
    }

    private function inCurrentOrganization(SsoProvider|SsoGroupMapping $record): bool
    {
        return $this->context->has() && $record->organization_id === $this->context->id();
    }
}
