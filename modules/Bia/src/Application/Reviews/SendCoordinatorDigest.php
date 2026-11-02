<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Reviews;

use Carbon\CarbonImmutable;
use Modules\Bia\Application\Notifications\CoordinatorDigest;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Weekly, per organization: what the BIA coordinators should chase — reviews
 * overdue, processes never assessed, assessments waiting for approval. Nothing
 * is sent when there is nothing to chase.
 */
final readonly class SendCoordinatorDigest
{
    public function __construct(
        private OrganizationContext $context,
        private BiaSummary $summary,
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
                $figures = $this->summary->figures($today);

                if ($figures['overdue'] + $figures['never_assessed'] + $figures['awaiting_approval'] === 0) {
                    return;
                }

                $notification = new CoordinatorDigest($figures['overdue'], $figures['never_assessed'], $figures['awaiting_approval'], route('bia.processes.index', ['due' => 1]));

                $recipients = User::query()->where('is_active', true)->whereHas('roles')->get()
                    ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('bia.assessment.approve'));

                $recipients->each(fn (User $user) => $user->notify($notification));
                $sent += $recipients->isNotEmpty() ? 1 : 0;
            });
        }

        return $sent;
    }
}
