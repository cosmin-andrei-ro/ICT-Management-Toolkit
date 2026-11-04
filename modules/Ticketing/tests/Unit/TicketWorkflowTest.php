<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Workflow\InvalidTransition;
use Modules\Ticketing\Domain\Workflow\PriorityMatrix;
use Modules\Ticketing\Domain\Workflow\TicketWorkflow;

it('allows the ticket lifecycle', function (TicketStatus $from, TicketStatus $to): void {
    expect((new TicketWorkflow)->canTransition($from, $to))->toBeTrue();
})->with([
    [TicketStatus::New, TicketStatus::Assigned],
    [TicketStatus::Assigned, TicketStatus::InProgress],
    [TicketStatus::InProgress, TicketStatus::Pending],
    [TicketStatus::Pending, TicketStatus::InProgress],
    [TicketStatus::InProgress, TicketStatus::Resolved],
    [TicketStatus::Resolved, TicketStatus::InProgress],
    [TicketStatus::Resolved, TicketStatus::Closed],
    [TicketStatus::New, TicketStatus::Cancelled],
]);

it('rejects skipping or leaving final states', function (TicketStatus $from, TicketStatus $to): void {
    (new TicketWorkflow)->assertCanTransition($from, $to);
})->with([
    'close before resolving' => [TicketStatus::InProgress, TicketStatus::Closed],
    'reopen a closed ticket' => [TicketStatus::Closed, TicketStatus::InProgress],
    'revive a cancelled ticket' => [TicketStatus::Cancelled, TicketStatus::New],
    'cancel a resolved ticket' => [TicketStatus::Resolved, TicketStatus::Cancelled],
])->throws(InvalidTransition::class);

it('has no way out of final states', function (TicketStatus $status): void {
    expect((new TicketWorkflow)->allowedFrom($status))->toBe([]);
})->with([TicketStatus::Closed, TicketStatus::Cancelled]);

it('derives priority from impact and urgency', function (Impact $impact, Urgency $urgency, Priority $priority): void {
    expect(PriorityMatrix::defaultFor($impact, $urgency))->toBe($priority);
})->with([
    [Impact::High, Urgency::High, Priority::Critical],
    [Impact::High, Urgency::Low, Priority::Medium],
    [Impact::Medium, Urgency::Medium, Priority::Medium],
    [Impact::Low, Urgency::High, Priority::Medium],
    [Impact::Low, Urgency::Low, Priority::Low],
]);
