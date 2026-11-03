<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Webhooks;

use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Domain\Webhooks\WebhookEvents;

/**
 * Continuity events for outbound webhooks (ADR 0066). Each carries its plan's
 * API link, where the version in force, exercises and activations are read.
 */
final class BcmWebhooks
{
    public const array EVENTS = [
        'continuity.plan_approved' => 'bcm::webhooks.plan_approved',
        'continuity.plan_activated' => 'bcm::webhooks.plan_activated',
        'continuity.activation_closed' => 'bcm::webhooks.activation_closed',
        'continuity.exercise_completed' => 'bcm::webhooks.exercise_completed',
    ];

    public static function register(WebhookEvents $events): void
    {
        $events->registerMany(self::EVENTS);
    }

    public static function listen(): void
    {
        PlanVersion::updated(function (PlanVersion $version): void {
            if ($version->wasChanged('status') && $version->status === VersionStatus::Approved) {
                self::publish('continuity.plan_approved', $version->plan_id, $version->id, ['version' => $version->version]);
            }
        });
        Activation::created(fn (Activation $activation) => self::publish('continuity.plan_activated', $activation->plan_id, $activation->id, ['scenario' => $activation->scenario?->value]));
        Activation::updated(function (Activation $activation): void {
            if ($activation->wasChanged('closed_at') && $activation->closed_at !== null) {
                self::publish('continuity.activation_closed', $activation->plan_id, $activation->id);
            }
        });
        Exercise::updated(function (Exercise $exercise): void {
            if ($exercise->wasChanged('status') && $exercise->status === ExerciseStatus::Completed) {
                self::publish('continuity.exercise_completed', $exercise->plan_id, $exercise->id, ['outcome' => $exercise->outcome?->value]);
            }
        });
    }

    /**
     * @param  string  $recordId  The version, activation or exercise the event is about.
     * @param  array<string, mixed>  $changes
     */
    private static function publish(string $type, string $planId, string $recordId, array $changes = []): void
    {
        $plan = Plan::query()->withoutGlobalScopes()->find($planId);

        if ($plan === null) {
            return;
        }

        app(PublishWebhookEvent::class)->handle($type, [
            'id' => $plan->id,
            'reference' => $plan->type->value,
            'title' => $plan->name,
            'url' => route('api.v1.continuity.plans.show', $plan->id),
            'changes' => ['record_id' => $recordId, ...$changes],
        ], $plan->organization_id);
    }
}
