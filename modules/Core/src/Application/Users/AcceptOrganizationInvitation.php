<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use DomainException;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Models\OrganizationInvitation;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Sso\SsoIdentity;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The invited person's answer to an invitation from another organization
 * (InviteUser). Only they can give it, signed in to their own account.
 */
final readonly class AcceptOrganizationInvitation
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    /**
     * @throws DomainException when the account signs in through another organization's single sign-on.
     */
    public function accept(OrganizationInvitation $invitation, User $person): void
    {
        $this->ensureTheirs($invitation, $person);

        // Another organization's identity provider controls this account: it must not reach this one.
        if ($this->signsInThroughAnotherOrganization($person, $invitation->organization_id)) {
            throw new DomainException('This account signs in through another organization\'s single sign-on.');
        }

        $this->db->transaction(function () use ($invitation, $person): void {
            $organizationId = $invitation->organization_id;

            if (! $person->isMemberOf($organizationId)) {
                $person->organizations()->attach($organizationId);
            }

            // Roles that no longer exist, or now belong elsewhere, are left out.
            $roles = Role::query()->whereKey($invitation->role_ids)
                ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))
                ->get();
            $this->context->runAs($organizationId, fn () => $person->assignRole($roles));
            $invitation->delete();
        });
    }

    public function decline(OrganizationInvitation $invitation, User $person): void
    {
        $this->ensureTheirs($invitation, $person);
        $invitation->delete();
    }

    private function ensureTheirs(OrganizationInvitation $invitation, User $person): void
    {
        if ($invitation->user_id !== $person->id || $invitation->expires_at->isPast()) {
            throw new DomainException('This invitation is not for you, or it expired.');
        }
    }

    private function signsInThroughAnotherOrganization(User $person, string $organizationId): bool
    {
        return SsoIdentity::query()->where('user_id', $person->id)
            ->whereIn('provider_id', SsoProvider::query()->withoutGlobalScopes()->where('organization_id', '!=', $organizationId)->select('id'))
            ->exists();
    }
}
