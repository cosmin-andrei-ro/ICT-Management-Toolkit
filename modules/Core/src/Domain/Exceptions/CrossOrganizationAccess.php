<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use LogicException;

final class CrossOrganizationAccess extends LogicException
{
    public static function onCreate(string $model): self
    {
        return new self("Cannot create {$model} in an organization other than the current one.");
    }

    public static function onMove(string $model): self
    {
        return new self("Cannot move {$model} to another organization.");
    }
}
