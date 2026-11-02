<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Reviews;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Query\Expression;
use Modules\Bia\Application\Notifications\ReviewDue;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Daily, across organizations: emails the owner and the deputy of each active
 * process whose review date is near or past, once per review date. A process
 * already under review is left alone.
 */
final readonly class SendReviewReminders
{
    public function __construct(
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return int Processes announced.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = $this->config->get('bia.reviews.reminder_days');
        $days = is_int($days) && $days > 0 ? $days : 30;
        $announced = 0;

        BusinessProcess::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', 'active')
            ->whereNotNull('next_review_on')
            ->where('next_review_on', '<=', $today->addDays($days)->toDateString())
            ->where(fn ($query) => $query->whereNull('review_reminded_for')->orWhereColumn('review_reminded_for', '!=', 'next_review_on'))
            ->orderBy('id')
            ->each(function (BusinessProcess $process) use (&$announced): void {
                $this->context->runAs($process->organization_id, function () use ($process, &$announced): void {
                    $underReview = Assessment::query()->where('process_id', $process->id)->whereIn('status', ['draft', 'submitted', 'returned'])->exists();

                    if (! $underReview) {
                        $notification = new ReviewDue($process->name, (string) $process->next_review_on?->toDateString(), route('bia.processes.show', $process));

                        User::query()->whereKey(array_filter([$process->owner_id, $process->deputy_id]))->get()
                            ->filter(fn (User $user): bool => $user->canSignIn())
                            ->each(fn (User $user) => $user->notify($notification));
                        $announced++;
                    }

                    // Bookkeeping, not a change of the process: kept out of the audit trail on purpose.
                    BusinessProcess::query()->whereKey($process->id)->update(['review_reminded_for' => new Expression('next_review_on')]);
                });
            });

        return $announced;
    }
}
