<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Reminders;

use Carbon\CarbonImmutable;
use Modules\Bcm\Application\Coverage\ContinuityCoverage;
use Modules\Bcm\Application\Notifications\ContinuityDigest;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Weekly, per organization: what the continuity coordinators should chase.
 * Nothing is sent when there is nothing to chase.
 */
final readonly class SendContinuityDigest
{
    private const array CHASED = ['uncovered', 'not_in_force', 'review_overdue', 'exercise_overdue', 'awaiting_approval', 'open_findings', 'open_activations'];

    public function __construct(
        private OrganizationContext $context,
        private ContinuityCoverage $coverage,
    ) {}

    /**
     * @return int Organizations whose coordinators were emailed.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;

        foreach (Organization::query()->orderBy('id')->get(['id']) as $organization) {
            $this->context->runAs($organization->id, function () use ($today, &$sent): void {
                $figures = array_filter(
                    array_intersect_key($this->coverage->figures($today), array_flip(self::CHASED)),
                    fn (int $count): bool => $count > 0,
                );

                if ($figures === []) {
                    return;
                }

                $notification = new ContinuityDigest($figures, route('bcm.coverage'));
                $recipients = User::query()->where('is_active', true)->whereHas('roles')->get()
                    ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('bcm.plan.approve'));

                $recipients->each(fn (User $user) => $user->notify($notification));
                $sent += $recipients->isNotEmpty() ? 1 : 0;
            });
        }

        return $sent;
    }
}
