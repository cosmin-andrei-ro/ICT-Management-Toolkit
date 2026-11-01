<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Users;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Takes a person out of the current organization: their roles there and their
 * membership. The account itself stays (it may belong to other organizations,
 * and its history must remain attributable).
 */
final readonly class RemoveFromOrganization
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private AuditTrail $trail,
    ) {}

    public function handle(User $user): void
    {
        $organizationId = $this->context->requireId();

        $this->db->transaction(function () use ($user, $organizationId): void {
            $user->syncRoles([]);
            $user->organizations()->detach($organizationId);

            if ($user->current_organization_id === $organizationId) {
                $user->forceFill(['current_organization_id' => $user->organizations()->value('core_organizations.id')])->save();
            }

            // Pivot writes bypass the model observer.
            $this->trail->record(AuditEvent::Updated, $user, ['organizations' => [$organizationId]], ['organizations' => []]);
        });
    }
}
