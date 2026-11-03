<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Modules\Core\Application\Webhooks\DeliverWebhook;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Domain\Models\WebhookDelivery;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Webhooks\PublishTicketWebhooks;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Queue::fake();
    $this->desk = TicketingScenario::create();
});

it('offers the ticket and change events to webhooks', function (): void {
    expect(array_keys(app(WebhookEvents::class)->all()))->toContain(...array_keys(PublishTicketWebhooks::EVENTS));
});

it('publishes ticket events with the number, the title and the API link, nothing more', function (): void {
    Webhook::factory()->create(['events' => ['ticket.created', 'ticket.assigned', 'ticket.status_changed', 'ticket.resolved']]);

    $ticket = $this->desk->ticket(title: 'Printer on floor 2 is offline');
    app(AssignTicket::class)->handle($ticket, $this->desk->agent, null, $this->desk->lead);
    app(ResolveTicket::class)->handle($ticket->refresh(), ResolutionCode::Fixed, 'Replaced the toner.', $this->desk->agent);

    $deliveries = WebhookDelivery::query()->get();
    expect($deliveries->pluck('event')->unique()->values()->all())->toEqualCanonicalizing(['ticket.created', 'ticket.status_changed', 'ticket.assigned', 'ticket.resolved'])
        ->and($deliveries->firstWhere('event', 'ticket.created')->payload['data'])->toEqual([
            'id' => $ticket->id,
            'reference' => $ticket->number,
            'title' => 'Printer on floor 2 is offline',
            'url' => route('api.v1.tickets.show', $ticket),
        ])
        ->and($deliveries->firstWhere('event', 'ticket.status_changed')->payload['data']['changes']['status'])->toHaveKeys(['from', 'to']);
    expect(json_encode($deliveries->pluck('payload')->all()))->not->toContain('Replaced the toner.')->not->toContain($this->desk->requester->email);
    Queue::assertPushed(DeliverWebhook::class, $deliveries->count());
});

it('publishes nothing when no webhook subscribes', function (): void {
    Webhook::factory()->create(['events' => ['change.approved']]);

    $this->desk->ticket();

    expect(WebhookDelivery::query()->count())->toBe(0);
});
