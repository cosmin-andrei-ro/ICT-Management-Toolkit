<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Raises a service request from the catalog: answers are validated against the
 * service's form and stored with their labels; routing and priority come from
 * the service.
 */
final readonly class RequestService
{
    public const int MAX_DETAILS = 5000;

    public function __construct(
        private CreateTicket $createTicket,
        private ValidationFactory $validation,
    ) {}

    /**
     * @param  array<string, mixed>  $answers  field key => value
     *
     * @throws ValidationException keyed by field key
     */
    public function handle(Service $service, array $answers, string $details, User $requester, TicketChannel $channel = TicketChannel::Portal): Ticket
    {
        if (! $service->is_active) {
            throw new InvalidArgumentException('This service cannot be requested.');
        }

        $details = trim($details);

        if (mb_strlen($details) > self::MAX_DETAILS) {
            throw ValidationException::withMessages(['details' => __('validation.max.string', ['attribute' => 'details', 'max' => self::MAX_DETAILS])]);
        }

        $stored = $service->form()->answer($answers, $this->validation);

        return $this->createTicket->handle(new NewTicketData(
            type: TicketType::ServiceRequest,
            title: $service->name,
            // The description stays readable on its own; answers are shown separately.
            description: $details !== '' ? $details : __('ticketing::catalog.requested', ['service' => $service->name]),
            impact: $service->impact,
            urgency: $service->urgency,
            channel: $channel,
            // A queue or category deactivated since must not block requests: fall back to the defaults.
            categoryId: $service->category?->is_active === true ? $service->category_id : null,
            queueId: $service->queue?->is_active === true ? $service->queue_id : null,
            serviceId: $service->id,
            customFields: ['service_form' => $stored],
        ), $requester, $requester);
    }
}
