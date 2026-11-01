<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Enums;

/**
 * Mirrored by ticketing_tickets_type_check. Problems and changes arrive with their
 * own workflows in later stages.
 */
enum TicketType: string
{
    case Incident = 'incident';
    case ServiceRequest = 'service_request';

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Incident => 'INC',
            self::ServiceRequest => 'REQ',
        };
    }

    public function label(): string
    {
        return __('ticketing::tickets.types.'.$this->value);
    }
}
