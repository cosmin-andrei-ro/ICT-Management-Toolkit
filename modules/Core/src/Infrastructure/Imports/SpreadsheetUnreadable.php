<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Imports;

use RuntimeException;

/**
 * A short reason code ("no_header", "too_many_rows"), for callers to translate.
 */
final class SpreadsheetUnreadable extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Spreadsheet cannot be imported: '.$reason);
    }
}
