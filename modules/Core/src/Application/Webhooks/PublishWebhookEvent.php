<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Webhooks;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Domain\Models\WebhookDelivery;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Core\Domain\Webhooks\WebhookEvents;

/**
 * Hands an event of the current organization to every active webhook that
 * subscribes to it (ADR 0066). The payload carries the essentials and the
 * record's API link, never the record itself: receivers fetch what they need
 * with their own token, under its abilities.
 */
final readonly class PublishWebhookEvent
{
    public function __construct(private OrganizationContext $context, private WebhookEvents $events) {}

    /**
     * @param  array{id: string, reference?: ?string, title?: ?string, url?: ?string, changes?: array<string, mixed>}  $subject
     * @param  ?string  $organizationId  The record's organization, for events raised outside a request (scheduler, queue).
     * @param  ?Webhook  $only  For a test: this webhook only, whatever it subscribes to.
     * @return int Deliveries queued.
     */
    public function handle(string $event, array $subject, ?string $organizationId = null, ?Webhook $only = null): int
    {
        if (! $this->events->has($event) && $only === null) {
            throw new InvalidArgumentException("Unknown webhook event [{$event}].");
        }

        $organizationId ??= $only->organization_id ?? $this->context->id();

        if ($organizationId === null) {
            return 0;
        }

        $webhooks = $only !== null ? collect([$only]) : Webhook::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organizationId)->where('is_active', true)->get()->filter(fn (Webhook $webhook): bool => $webhook->listensTo($event));
        $queued = 0;

        foreach ($webhooks as $webhook) {
            $id = (string) Str::uuid7();
            $delivery = new WebhookDelivery;
            $delivery->forceFill([
                'id' => $id,
                'organization_id' => $organizationId,
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => [
                    'id' => $id,
                    'event' => $event,
                    'occurred_at' => now()->toIso8601String(),
                    'organization_id' => $organizationId,
                    'data' => $subject,
                ],
            ])->save();

            // Sent once the change that caused it is committed: a rolled back change sends nothing.
            DeliverWebhook::dispatch($id)->afterCommit();
            $queued++;
        }

        return $queued;
    }
}
