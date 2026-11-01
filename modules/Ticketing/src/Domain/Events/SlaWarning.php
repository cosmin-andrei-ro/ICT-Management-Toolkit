<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Ticketing\Domain\Models\SlaTimer;

/**
 * Three quarters of an SLA target are used. Dispatched after the transaction commits.
 */
final readonly class SlaWarning implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public SlaTimer $timer) {}
}
