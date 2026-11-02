<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Exceptions;

use DomainException;
use Modules\Itam\Domain\Enums\AssetStatus;

final class AssetTransitionNotAllowed extends DomainException
{
    public static function between(AssetStatus $from, AssetStatus $to): self
    {
        return new self("An asset cannot go from [{$from->value}] to [{$to->value}].");
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
