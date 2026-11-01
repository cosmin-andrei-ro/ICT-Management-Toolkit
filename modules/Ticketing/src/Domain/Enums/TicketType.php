<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Enums;

/**
 * Mirrored by ticketing_tickets_type_check. Changes arrive with their own workflow
 * in a later stage.
 */
enum TicketType: string
{
    case Incident = 'incident';
    case ServiceRequest = 'service_request';
    case Problem = 'problem';

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Incident => 'INC',
            self::ServiceRequest => 'REQ',
            self::Problem => 'PRB',
        };
    }

    /**
     * Types people may raise themselves (portal, API). Problems are opened by the
     * service desk only.
     *
     * @return list<self>
     */
    public static function requestable(): array
    {
        return [self::Incident, self::ServiceRequest];
    }

    public function hasSla(): bool
    {
        return $this !== self::Problem;
    }

    public function label(): string
    {
        return __('ticketing::tickets.types.'.$this->value);
    }
}
