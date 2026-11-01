<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Directory;

use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryUser;

/**
 * Bound when LDAP_ENABLED is false: no account is ever found in the directory.
 */
final class DisabledDirectory implements Directory
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function findUser(string $identifier): ?DirectoryUser
    {
        return null;
    }

    public function findByGuid(string $guid): ?DirectoryUser
    {
        return null;
    }

    public function authenticate(DirectoryUser $user, string $password): bool
    {
        return false;
    }

    public function groupsOf(DirectoryUser $user): array
    {
        return [];
    }
}
