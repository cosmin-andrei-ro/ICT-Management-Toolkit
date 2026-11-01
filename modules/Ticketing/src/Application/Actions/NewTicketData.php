<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;

final readonly class NewTicketData
{
    public function __construct(
        public TicketType $type,
        public string $title,
        public string $description,
        public Impact $impact = Impact::Low,
        public Urgency $urgency = Urgency::Low,
        public TicketChannel $channel = TicketChannel::Portal,
        public ?string $categoryId = null,
        public ?string $queueId = null,
    ) {}
}
