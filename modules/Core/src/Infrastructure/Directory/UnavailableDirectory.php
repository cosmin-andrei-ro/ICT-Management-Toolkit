<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Directory;

use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Directory\DirectoryUser;

/**
 * Bound when AD is enabled but misconfigured: directory sign-ins report the directory
 * as unavailable, while local accounts (e.g. the administrator who has to fix the
 * configuration) can still sign in.
 */
final readonly class UnavailableDirectory implements Directory
{
    public function __construct(private string $reason) {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function findUser(string $identifier): ?DirectoryUser
    {
        throw new DirectoryUnavailable($this->reason);
    }

    public function findByGuid(string $guid): ?DirectoryUser
    {
        throw new DirectoryUnavailable($this->reason);
    }

    public function authenticate(DirectoryUser $user, string $password): bool
    {
        throw new DirectoryUnavailable($this->reason);
    }

    public function groupsOf(DirectoryUser $user): array
    {
        throw new DirectoryUnavailable($this->reason);
    }
}
