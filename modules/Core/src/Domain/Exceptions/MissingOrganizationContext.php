<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use LogicException;

final class MissingOrganizationContext extends LogicException
{
    public function __construct()
    {
        parent::__construct('No organization is set in the current context.');
    }
}
