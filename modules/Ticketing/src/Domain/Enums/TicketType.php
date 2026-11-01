<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Enums;

/**
 * Mirrored by ticketing_tickets_type_check.
 */
enum TicketType: string
{
    case Incident = 'incident';
    case ServiceRequest = 'service_request';
    case Problem = 'problem';
    case Change = 'change';

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Incident => 'INC',
            self::ServiceRequest => 'REQ',
            self::Problem => 'PRB',
            self::Change => 'CHG',
        };
    }

    /**
     * Types people may raise themselves (portal, API). Problems and changes are
     * opened by the service desk only.
     *
     * @return list<self>
     */
    public static function requestable(): array
    {
        return [self::Incident, self::ServiceRequest];
    }

    public function hasSla(): bool
    {
        // Problems are investigated and changes are scheduled: neither has response targets.
        return $this === self::Incident || $this === self::ServiceRequest;
    }

    public function label(): string
    {
        return __('ticketing::tickets.types.'.$this->value);
    }
}
