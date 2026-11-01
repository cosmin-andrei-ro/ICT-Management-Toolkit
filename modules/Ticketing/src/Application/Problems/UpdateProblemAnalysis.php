<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Problems;

use InvalidArgumentException;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\ProblemDetails;
use Modules\Ticketing\Domain\Models\Ticket;

final readonly class UpdateProblemAnalysis
{
    public const int MAX_LENGTH = 20_000;

    public function handle(Ticket $problem, string $rootCause, string $workaround): void
    {
        $details = self::details($problem);
        $rootCause = trim($rootCause);
        $workaround = trim($workaround);

        if (mb_strlen($rootCause) > self::MAX_LENGTH || mb_strlen($workaround) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('The analysis is too long.');
        }

        if ($details->isKnownError() && $workaround === '') {
            throw new InvalidArgumentException('A known error keeps its workaround.');
        }

        $details->root_cause = $rootCause !== '' ? $rootCause : null;
        $details->workaround = $workaround !== '' ? $workaround : null;
        $details->save();
    }

    public static function details(Ticket $problem): ProblemDetails
    {
        if ($problem->type !== TicketType::Problem || $problem->problemDetails === null) {
            throw new InvalidArgumentException('Not a problem.');
        }

        if ($problem->status->isFinal()) {
            throw new InvalidArgumentException('The problem is closed.');
        }

        return $problem->problemDetails;
    }
}
