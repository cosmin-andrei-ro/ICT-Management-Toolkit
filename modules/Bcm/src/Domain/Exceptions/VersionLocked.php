<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Exceptions;

use DomainException;

/**
 * A submitted, approved or superseded plan version does not change.
 */
final class VersionLocked extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only draft or returned plan versions can change.');
    }
}
