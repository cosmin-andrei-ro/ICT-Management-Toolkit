<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Directory;

/**
 * A user account as read from the directory. The GUID is the stable identity:
 * names, emails and DNs change when people are renamed or moved between OUs.
 */
final readonly class DirectoryUser
{
    public function __construct(
        public string $guid,
        public string $distinguishedName,
        public string $username,
        public string $name,
        public string $email,
        public bool $enabled,
    ) {}
}
