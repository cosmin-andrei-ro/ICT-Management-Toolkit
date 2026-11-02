<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Activations;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Bcm\Application\Notifications\PlanActivated;
use Modules\Bcm\Application\PlanTiming;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Domain\Models\TeamMember;
use Modules\Core\Domain\Models\User;

/**
 * Records that a plan in force was activated, keeping the version and the
 * strictest RTO of the moment, and optionally emails the crisis team members
 * who have an account, with the owner and the deputy, a link to the plan.
 */
final readonly class ActivatePlan
{
    public function __construct(
        private ConnectionInterface $db,
        private PlanTiming $timing,
    ) {}

    /**
     * @throws InvalidArgumentException when the plan has no version in force, or the activation is in the future
     */
    public function handle(Plan $plan, CarbonImmutable $activatedAt, ?Scenario $scenario, string $reason, bool $notifyTeam, User $actor): Activation
    {
        // Eloquent stores the wall-clock time without its offset.
        $activatedAt = $activatedAt->setTimezone(date_default_timezone_get());

        if ($activatedAt->isFuture()) {
            throw new InvalidArgumentException('An activation cannot be in the future.');
        }

        $version = PlanVersion::query()->where('plan_id', $plan->id)->where('status', VersionStatus::Approved)->first()
            ?? throw new InvalidArgumentException('Only a plan in force can be activated.');

        $activation = $this->db->transaction(function () use ($plan, $version, $activatedAt, $scenario, $reason, $actor): Activation {
            $activation = new Activation;
            $activation->forceFill([
                'organization_id' => $plan->organization_id,
                'plan_id' => $plan->id,
                'version_id' => $version->id,
                'scenario' => $scenario,
                'reason' => mb_substr(trim($reason), 0, 5000),
                'activated_at' => $activatedAt,
                'activated_by' => $actor->id,
                'rto_minutes' => $this->timing->strictestRto($version)?->rtoMinutes,
            ])->save();

            return $activation;
        });

        if ($notifyTeam) {
            $notification = new PlanActivated($plan->name, $activatedAt->toIso8601String(), $actor->name, route('bcm.plans.sheet', $plan), route('bcm.activations.show', $activation));
            $teamIds = TeamMember::query()->where('version_id', $version->id)->whereNotNull('user_id')->get(['user_id'])->map(fn (TeamMember $member): ?string => $member->user_id)->all();

            User::query()->whereKey(array_values(array_unique(array_filter([...$teamIds, $plan->owner_id, $plan->deputy_id], is_string(...)))))->get()
                ->filter(fn (User $user): bool => $user->canSignIn() && $user->id !== $actor->id)
                ->each(fn (User $user) => $user->notify($notification));
        }

        return $activation;
    }
}
