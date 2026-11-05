<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\CustomFields\CustomFieldSchema;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Administrators decide which extra fields exist.
 */
final readonly class CustomFieldSchemaPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.custom_fields.manage');
    }

    public function view(User $user, CustomFieldSchema $schema): bool
    {
        return $this->update($user, $schema);
    }

    public function create(User $user): bool
    {
        return $user->can('core.custom_fields.manage');
    }

    public function update(User $user, CustomFieldSchema $schema): bool
    {
        return $schema->organization_id === $this->context->id() && $user->can('core.custom_fields.manage');
    }

    public function delete(User $user, CustomFieldSchema $schema): bool
    {
        return $this->update($user, $schema);
    }
}
