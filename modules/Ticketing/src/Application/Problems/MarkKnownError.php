<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Problems;

use InvalidArgumentException;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * A problem whose cause and workaround are documented. From then on, agents
 * find it in the known error database and get its workaround when they link an incident.
 */
final readonly class MarkKnownError
{
    public function handle(Ticket $problem): void
    {
        $details = UpdateProblemAnalysis::details($problem);

        if (trim((string) $details->workaround) === '') {
            throw new InvalidArgumentException('Document a workaround first.');
        }

        $details->known_error_at ??= now();
        $details->save();
    }
}
