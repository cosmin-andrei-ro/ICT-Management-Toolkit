<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Webhooks;

use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Itam\Domain\Models\Asset;

/**
 * Asset events for outbound webhooks (ADR 0066): handed to someone, or back.
 * The holder is not named; the receiver reads it through the API.
 */
final class ItamWebhooks
{
    public const array EVENTS = [
        'asset.assigned' => 'itam::webhooks.assigned',
        'asset.returned' => 'itam::webhooks.returned',
    ];

    public static function register(WebhookEvents $events): void
    {
        $events->registerMany(self::EVENTS);
    }

    public static function listen(): void
    {
        Asset::created(function (Asset $asset): void {
            if ($asset->assigned_to_id !== null) {
                self::publish('asset.assigned', $asset);
            }
        });
        Asset::updated(function (Asset $asset): void {
            if (! $asset->wasChanged('assigned_to_id')) {
                return;
            }

            // Handed straight from one person to another counts as assigned.
            self::publish($asset->assigned_to_id !== null ? 'asset.assigned' : 'asset.returned', $asset);
        });
    }

    private static function publish(string $type, Asset $asset): void
    {
        app(PublishWebhookEvent::class)->handle($type, [
            'id' => $asset->id,
            'reference' => $asset->asset_tag,
            'title' => $asset->name ?? $asset->asset_tag,
            'url' => route('api.v1.assets.show', $asset->id),
        ], $asset->organization_id);
    }
}
