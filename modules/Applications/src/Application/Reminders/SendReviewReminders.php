<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Reminders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Query\Expression;
use Modules\Applications\Application\Notifications\ReviewDue;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Daily, across organizations: emails the owners and the deputy of each
 * application whose documentation is due for review (at least yearly, as the
 * template asks), once per review date. A version already being written
 * silences it.
 */
final readonly class SendReviewReminders
{
    public function __construct(
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return int Applications announced.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = $this->config->get('applications.sheets.review_reminder_days');
        $horizon = $today->addDays(is_int($days) && $days > 0 ? $days : 30)->toDateString();
        $sent = 0;

        Application::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('next_review_on')
            ->where('next_review_on', '<=', $horizon)
            ->where(fn ($query) => $query->whereNull('review_reminded_for')->orWhereColumn('review_reminded_for', '!=', 'next_review_on'))
            ->orderBy('id')
            ->each(function (Application $application) use (&$sent): void {
                $this->context->runAs($application->organization_id, function () use ($application, &$sent): void {
                    $writing = Sheet::query()->where('application_id', $application->id)->whereIn('status', ['draft', 'submitted', 'returned'])->exists();

                    if (! $writing) {
                        $notification = new ReviewDue($application->name, (string) $application->next_review_on?->toDateString(), route('applications.show', $application));

                        User::query()->whereKey(array_values(array_filter([$application->business_owner_id, $application->technical_owner_id, $application->deputy_id])))->get()
                            ->filter(fn (User $user): bool => $user->canSignIn())
                            ->each(fn (User $user) => $user->notify($notification));
                        $sent++;
                    }

                    // Bookkeeping, not a change of the application: kept out of the audit trail on purpose.
                    Application::query()->whereKey($application->id)->update(['review_reminded_for' => new Expression('next_review_on')]);
                });
            });

        return $sent;
    }
}
