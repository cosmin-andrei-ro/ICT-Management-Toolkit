<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Imports;

use RuntimeException;

final class ImportHasErrors extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The file has rows with errors; nothing was imported.');
    }
}
