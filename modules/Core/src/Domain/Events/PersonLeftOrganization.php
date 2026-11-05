<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

use Modules\Core\Domain\Models\User;

/**
 * Someone stopped being a member of an organization. Modules drop what only made
 * sense while they were one, so that re-adding them does not silently restore it.
 */
final readonly class PersonLeftOrganization
{
    public function __construct(
        public User $user,
        public string $organizationId,
    ) {}
}
