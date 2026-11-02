<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Bcm\Application\Notifications\PlanUpdate;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Domain\Models\User;

/**
 * Approves a submitted plan version, or returns it to the owner with a reason.
 * Approval supersedes the version in force and sets the next review date.
 */
final readonly class DecidePlanVersion
{
    public function __construct(
        private ConnectionInterface $db,
        private Gate $gate,
        private Config $config,
    ) {}

    /**
     * @throws AuthorizationException for the owner, the submitter, or someone without the right
     * @throws ValidationException keyed "comment" when returning without a reason
     */
    public function handle(PlanVersion $version, User $actor, bool $approve, ?string $comment): PlanVersion
    {
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        if (! $approve && $comment === null) {
            throw ValidationException::withMessages(['comment' => __('bcm::plans.errors.return_reason')]);
        }

        $version = $this->db->transaction(function () use ($version, $actor, $approve, $comment): PlanVersion {
            $version = PlanVersion::query()->with('plan')->lockForUpdate()->findOrFail($version->id);

            // Checked again under the lock: the status may have changed since the page was shown.
            if (! $this->gate->forUser($actor)->allows('decide', $version)) {
                throw new AuthorizationException;
            }

            if (! $approve) {
                $version->forceFill(['status' => VersionStatus::Returned, 'decided_at' => now(), 'decided_by' => $actor->id, 'decision_comment' => $comment])->save();

                return $version;
            }

            PlanVersion::query()->where('plan_id', $version->plan_id)->where('status', VersionStatus::Approved->value)
                ->get()->each(fn (PlanVersion $previous) => $previous->forceFill(['status' => VersionStatus::Superseded])->save());

            $version->forceFill(['status' => VersionStatus::Approved, 'decided_at' => now(), 'decided_by' => $actor->id, 'decision_comment' => $comment])->save();

            $months = $this->config->get('bcm.plans.review_months');
            Plan::query()->findOrFail($version->plan_id)->forceFill([
                'approved_version' => $version->version,
                'approved_at' => now(),
                'next_review_on' => now()->addMonthsNoOverflow(is_int($months) && $months > 0 ? $months : 12)->toDateString(),
            ])->save();

            return $version;
        });

        $notification = new PlanUpdate($approve ? 'approved' : 'returned', $version->plan->name, $version->version, route('bcm.versions.show', $version), $comment);

        collect([$version->plan->owner, $version->plan->deputy, $version->submitter])
            ->filter(fn (?User $user): bool => $user !== null && $user->canSignIn() && $user->id !== $actor->id)
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));

        return $version;
    }
}
