<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Domain\Exceptions;

use RuntimeException;

/**
 * PAM_KEY is missing, invalid, or the same as APP_KEY.
 */
final class VaultNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The vault has no valid key of its own (PAM_KEY).');
    }
}
