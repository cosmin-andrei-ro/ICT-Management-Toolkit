<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Exceptions;

use DomainException;

/**
 * A submitted, approved or superseded assessment does not change.
 */
final class AssessmentLocked extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only draft or returned assessments can change.');
    }
}
