<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Directory;

interface Directory
{
    public function isEnabled(): bool;

    /**
     * Finds an account by username (sAMAccountName, optionally DOMAIN\ prefixed),
     * user principal name or email. Returns null when none or several match.
     *
     * @throws DirectoryUnavailable
     */
    public function findUser(string $identifier): ?DirectoryUser;

    /**
     * Verifies the password by binding as the user. An empty password is always
     * rejected: LDAP treats it as an unauthenticated bind, which "succeeds".
     *
     * @throws DirectoryUnavailable
     */
    public function authenticate(DirectoryUser $user, string $password): bool;

    /**
     * Distinguished names of every group the user belongs to, including through
     * nested groups, lowercased.
     *
     * @return list<string>
     *
     * @throws DirectoryUnavailable
     */
    public function groupsOf(DirectoryUser $user): array;
}
