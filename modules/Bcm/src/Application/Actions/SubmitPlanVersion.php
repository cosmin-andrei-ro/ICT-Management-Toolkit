<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Bcm\Application\Notifications\PlanUpdate;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Exceptions\VersionLocked;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;

/**
 * Sends a complete plan version for approval and tells the approvers. Complete:
 * a scope (processes or systems), at least one scenario, activation criteria,
 * a crisis team and a runbook.
 */
final readonly class SubmitPlanVersion
{
    public function __construct(
        private ConnectionInterface $db,
        private ConfigurationItems $items,
    ) {}

    /**
     * @throws VersionLocked
     * @throws ValidationException listing what is missing
     */
    public function handle(PlanVersion $version, User $actor): PlanVersion
    {
        $version = $this->db->transaction(function () use ($version, $actor): PlanVersion {
            $version = PlanVersion::query()->with('plan')->lockForUpdate()->findOrFail($version->id);

            if (! $version->status->isEditable()) {
                throw new VersionLocked;
            }

            $errors = [];
            $items = $this->items->linkedIds($version->getMorphClass(), [$version->id])[$version->id] ?? [];

            if ($version->processIds() === [] && $items === []) {
                $errors['scope'] = __('bcm::plans.errors.scope');
            }

            if ($version->scenarios === []) {
                $errors['scenarios'] = __('bcm::plans.errors.scenarios');
            }

            if ($version->activation_criteria === null) {
                $errors['activationCriteria'] = __('bcm::plans.errors.activation');
            }

            if (! $version->team()->exists()) {
                $errors['team'] = __('bcm::plans.errors.team');
            }

            if (! $version->steps()->exists()) {
                $errors['steps'] = __('bcm::plans.errors.steps');
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $version->forceFill([
                'status' => VersionStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
                'decided_at' => null,
                'decided_by' => null,
                'decision_comment' => null,
            ])->save();

            return $version;
        });

        $notification = new PlanUpdate('submitted', $version->plan->name, $version->version, route('bcm.versions.show', $version));

        User::query()->where('is_active', true)->whereHas('roles')->get()
            ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('bcm.plan.approve') && $user->id !== $actor->id && ! $version->plan->isOwnedBy($user))
            ->each(fn (User $user) => $user->notify($notification));

        return $version;
    }
}
