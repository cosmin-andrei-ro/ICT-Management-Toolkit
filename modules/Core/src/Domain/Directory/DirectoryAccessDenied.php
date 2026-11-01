<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Directory;

use RuntimeException;

/**
 * The directory accepted the credentials, but the account may not use the application.
 */
final class DirectoryAccessDenied extends RuntimeException
{
    public const string ACCOUNT_DISABLED = 'account_disabled';

    public const string NO_MAPPED_GROUP = 'no_mapped_group';

    public const string EMAIL_CONFLICT = 'email_conflict';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Directory access denied: {$reason}.");
    }
}
