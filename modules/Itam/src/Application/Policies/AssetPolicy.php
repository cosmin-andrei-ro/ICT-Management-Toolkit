<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\Asset;

/**
 * IT staff see assets (itam.asset.view) and hand them out (itam.asset.assign);
 * asset managers and team leads keep the register (itam.asset.manage). Holders
 * see their own equipment on "My equipment", not the asset page with its
 * invoices and costs.
 */
final readonly class AssetPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('itam.asset.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->inCurrentOrganization($asset) && $user->can('itam.asset.view');
    }

    /**
     * Asset documents are never internal; asked by AttachmentPolicy all the same.
     */
    public function viewInternal(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset);
    }

    public function create(User $user): bool
    {
        return $user->can('itam.asset.manage');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->inCurrentOrganization($asset) && $user->can('itam.asset.manage');
    }

    public function assign(User $user, Asset $asset): bool
    {
        return $this->inCurrentOrganization($asset) && $user->can('itam.asset.assign');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->inCurrentOrganization($asset) && $user->can('itam.asset.manage');
    }

    private function inCurrentOrganization(Asset $asset): bool
    {
        return $this->context->has() && $asset->organization_id === $this->context->id();
    }
}
