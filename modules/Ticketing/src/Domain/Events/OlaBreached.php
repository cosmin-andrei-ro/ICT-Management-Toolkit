<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Events;

use Modules\Ticketing\Domain\Models\OlaTimer;

/**
 * A ticket stayed with a team longer than the team's target.
 */
final readonly class OlaBreached
{
    public function __construct(public OlaTimer $timer) {}
}
