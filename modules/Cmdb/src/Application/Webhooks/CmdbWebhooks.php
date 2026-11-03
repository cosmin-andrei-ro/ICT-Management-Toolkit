<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Webhooks;

use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Domain\Webhooks\WebhookEvents;

/**
 * Configuration item events for outbound webhooks (ADR 0066). Raised by the
 * model, so the screens, the API and imports all publish them. Updates name
 * the fields that changed, never their values.
 */
final class CmdbWebhooks
{
    public const array EVENTS = [
        'cmdb.item.created' => 'cmdb::webhooks.item_created',
        'cmdb.item.updated' => 'cmdb::webhooks.item_updated',
        'cmdb.item.deleted' => 'cmdb::webhooks.item_deleted',
    ];

    /** Bookkeeping columns: a save that touches only these is not news. */
    private const array IGNORED = ['updated_at', 'updated_by', 'created_at', 'created_by'];

    public static function register(WebhookEvents $events): void
    {
        $events->registerMany(self::EVENTS);
    }

    public static function listen(): void
    {
        ConfigurationItem::created(fn (ConfigurationItem $item) => self::publish('cmdb.item.created', $item));
        ConfigurationItem::updated(function (ConfigurationItem $item): void {
            $fields = array_values(array_diff(array_keys($item->getChanges()), self::IGNORED));

            if ($fields !== []) {
                self::publish('cmdb.item.updated', $item, ['fields' => $fields]);
            }
        });
        ConfigurationItem::deleted(fn (ConfigurationItem $item) => self::publish('cmdb.item.deleted', $item));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function publish(string $type, ConfigurationItem $item, array $changes = []): void
    {
        app(PublishWebhookEvent::class)->handle($type, [
            'id' => $item->id,
            'reference' => $item->identifier,
            'title' => $item->name,
            'url' => route('api.v1.cmdb.items.show', $item->id),
            ...($changes !== [] ? ['changes' => $changes] : []),
        ], $item->organization_id);
    }
}
