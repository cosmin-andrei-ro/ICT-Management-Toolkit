<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Reminders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\Bcm\Application\Notifications\PlanDue;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Daily, across organizations: emails the owner and the deputy of each active
 * plan whose review or next exercise is near or past, once per due date. A
 * review under way, or an exercise planned, silences the reminder.
 */
final readonly class SendPlanReminders
{
    public function __construct(
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return array{reviews: int, exercises: int} Plans announced.
     */
    public function handle(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $horizon = $today->addDays($this->positive('bcm.plans.reminder_days', 30));
        $months = $this->positive('bcm.plans.exercise_months', 12);
        $sent = ['reviews' => 0, 'exercises' => 0];

        Plan::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', 'active')
            ->whereNotNull('approved_version')
            ->orderBy('id')
            ->each(function (Plan $plan) use ($horizon, $months, &$sent): void {
                $this->context->runAs($plan->organization_id, function () use ($plan, $horizon, $months, &$sent): void {
                    $reviewDue = $plan->next_review_on;

                    if ($reviewDue !== null && $reviewDue->lte($horizon) && $plan->review_reminded_for?->toDateString() !== $reviewDue->toDateString()) {
                        if (! $plan->versions()->whereIn('status', ['draft', 'submitted', 'returned'])->exists()) {
                            $this->notify($plan, new PlanDue('review', $plan->name, $reviewDue->toDateString(), route('bcm.plans.show', $plan)));
                            $sent['reviews']++;
                        }

                        // Bookkeeping, not a change of the plan: kept out of the audit trail on purpose.
                        Plan::query()->whereKey($plan->id)->update(['review_reminded_for' => $reviewDue->toDateString()]);
                    }

                    $exerciseDue = $plan->exerciseDueOn($months);

                    if ($exerciseDue !== null && $exerciseDue->lte($horizon) && $plan->exercise_reminded_for?->toDateString() !== $exerciseDue->toDateString()) {
                        if (! $plan->exercises()->whereIn('status', ['planned', 'in_progress'])->exists()) {
                            $this->notify($plan, new PlanDue('exercise', $plan->name, $exerciseDue->toDateString(), route('bcm.plans.show', $plan)));
                            $sent['exercises']++;
                        }

                        Plan::query()->whereKey($plan->id)->update(['exercise_reminded_for' => $exerciseDue->toDateString()]);
                    }
                });
            });

        return $sent;
    }

    private function notify(Plan $plan, PlanDue $notification): void
    {
        User::query()->whereKey(array_values(array_filter([$plan->owner_id, $plan->deputy_id])))->get()
            ->filter(fn (User $user): bool => $user->canSignIn())
            ->each(fn (User $user) => $user->notify($notification));
    }

    private function positive(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return is_int($value) && $value > 0 ? $value : $default;
    }
}
