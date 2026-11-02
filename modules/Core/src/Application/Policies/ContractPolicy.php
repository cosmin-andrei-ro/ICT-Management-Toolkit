<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Contracts show values and terms: only people allowed to see contracts
 * (core.contract.view) do, not every agent. Their attachments follow "view".
 */
final readonly class ContractPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.contract.view');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $this->inCurrentOrganization($contract) && $user->can('core.contract.view');
    }

    /**
     * Contract attachments are never internal; asked by AttachmentPolicy all the same.
     */
    public function viewInternal(User $user, Contract $contract): bool
    {
        return $this->view($user, $contract);
    }

    public function create(User $user): bool
    {
        return $user->can('core.contract.manage');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->inCurrentOrganization($contract) && $user->can('core.contract.manage');
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->inCurrentOrganization($contract) && $user->can('core.contract.manage');
    }

    private function inCurrentOrganization(Contract $contract): bool
    {
        return $this->context->has() && $contract->organization_id === $this->context->id();
    }
}
