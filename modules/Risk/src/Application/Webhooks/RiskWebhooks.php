<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Webhooks;

use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\ScoreRecord;

/**
 * Risk events for outbound webhooks (ADR 0066): a risk registered, its score
 * changed (each entry of the score history), an acceptance decided.
 */
final class RiskWebhooks
{
    public const array EVENTS = [
        'risk.registered' => 'risk::webhooks.registered',
        'risk.score_changed' => 'risk::webhooks.score_changed',
        'risk.acceptance_decided' => 'risk::webhooks.acceptance_decided',
    ];

    public static function register(WebhookEvents $events): void
    {
        $events->registerMany(self::EVENTS);
    }

    public static function listen(): void
    {
        Risk::created(fn (Risk $risk) => self::publish('risk.registered', $risk));
        ScoreRecord::created(function (ScoreRecord $record): void {
            $risk = Risk::query()->withoutGlobalScopes()->find($record->risk_id);

            if ($risk !== null) {
                self::publish('risk.score_changed', $risk, ['inherent_score' => $risk->inherentScore(), 'residual_score' => $risk->residualScore()]);
            }
        });
        // Created too: an acceptance within the owner's own authority is decided as it is requested.
        Acceptance::created(fn (Acceptance $acceptance) => self::acceptanceDecided($acceptance));
        Acceptance::updated(function (Acceptance $acceptance): void {
            if ($acceptance->wasChanged('status')) {
                self::acceptanceDecided($acceptance);
            }
        });
    }

    private static function acceptanceDecided(Acceptance $acceptance): void
    {
        if (! in_array($acceptance->status, [AcceptanceStatus::Approved, AcceptanceStatus::Rejected], true)) {
            return;
        }

        $risk = Risk::query()->withoutGlobalScopes()->find($acceptance->risk_id);

        if ($risk !== null) {
            self::publish('risk.acceptance_decided', $risk, ['decision' => $acceptance->status->value]);
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function publish(string $type, Risk $risk, array $changes = []): void
    {
        app(PublishWebhookEvent::class)->handle($type, [
            'id' => $risk->id,
            'reference' => $risk->code(),
            'title' => $risk->title,
            'url' => route('api.v1.risks.show', $risk->id),
            ...($changes !== [] ? ['changes' => $changes] : []),
        ], $risk->organization_id);
    }
}
