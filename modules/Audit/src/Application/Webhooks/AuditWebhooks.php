<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Webhooks;

use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Finding;
use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Domain\Webhooks\WebhookEvents;

/**
 * Finding events for outbound webhooks (ADR 0066): raised, and closed once
 * its actions are verified effective.
 */
final class AuditWebhooks
{
    public const array EVENTS = [
        'finding.raised' => 'audit::webhooks.finding_raised',
        'finding.closed' => 'audit::webhooks.finding_closed',
    ];

    public static function register(WebhookEvents $events): void
    {
        $events->registerMany(self::EVENTS);
    }

    public static function listen(): void
    {
        Finding::created(fn (Finding $finding) => self::publish('finding.raised', $finding));
        Finding::updated(function (Finding $finding): void {
            if ($finding->wasChanged('status') && $finding->status === FindingStatus::Closed) {
                self::publish('finding.closed', $finding);
            }
        });
    }

    private static function publish(string $type, Finding $finding): void
    {
        app(PublishWebhookEvent::class)->handle($type, [
            'id' => $finding->id,
            'reference' => $finding->code(),
            'title' => $finding->title,
            'url' => route('api.v1.compliance.findings.show', $finding->id),
            'changes' => ['severity' => $finding->severity->value],
        ], $finding->organization_id);
    }
}
