<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Directory;

use RuntimeException;

final class DirectorySyncAborted extends RuntimeException
{
    public function __construct(public readonly int $missing, public readonly int $total)
    {
        parent::__construct(
            "Directory sync aborted: {$missing} of {$total} accounts were not found in AD. "
            .'Check LDAP_BASE_DN and the service account permissions; use --force only if the removals are real.',
        );
    }
}
