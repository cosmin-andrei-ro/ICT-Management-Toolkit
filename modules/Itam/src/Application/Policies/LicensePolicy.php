<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Software products, licences and seats. IT staff see them and give seats out
 * (itam.license.view, .assign); asset managers and team leads keep the licences
 * (itam.license.manage) and may see their keys (itam.license.reveal_key).
 */
final readonly class LicensePolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('itam.license.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('itam.license.view');
    }

    /**
     * Licence documents are never internal; asked by AttachmentPolicy all the same.
     */
    public function viewInternal(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('itam.license.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('itam.license.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('itam.license.manage');
    }

    public function assign(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('itam.license.assign');
    }

    public function revealKey(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('itam.license.reveal_key');
    }

    private function inCurrentOrganization(Model $model): bool
    {
        return $this->context->has() && $model->getAttribute('organization_id') === $this->context->id();
    }
}
