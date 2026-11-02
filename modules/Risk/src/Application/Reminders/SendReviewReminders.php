<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Reminders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Query\Expression;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Risk\Application\Notifications\RiskUpdate;
use Modules\Risk\Domain\Models\Risk;

/**
 * Daily, across organizations: emails the owner of each open risk due for
 * reassessment, once per review date.
 */
final readonly class SendReviewReminders
{
    public function __construct(
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return int Risks announced.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = $this->config->get('risk.risks.reminder_days');
        $horizon = $today->addDays(is_int($days) && $days > 0 ? $days : 30)->toDateString();
        $sent = 0;

        Risk::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', 'open')
            ->whereNotNull('owner_id')
            ->whereNotNull('next_review_on')
            ->where('next_review_on', '<=', $horizon)
            ->where(fn ($query) => $query->whereNull('review_reminded_for')->orWhereColumn('review_reminded_for', '!=', 'next_review_on'))
            ->orderBy('id')
            ->each(function (Risk $risk) use (&$sent): void {
                $this->context->runAs($risk->organization_id, function () use ($risk, &$sent): void {
                    $owner = User::query()->find($risk->owner_id);

                    if ($owner !== null && $owner->canSignIn()) {
                        $owner->notify(new RiskUpdate('review_due', $risk->code().' '.$risk->title, route('risk.risks.show', $risk), (string) $risk->next_review_on?->toDateString()));
                        $sent++;
                    }

                    // Bookkeeping, not a change of the risk: kept out of the audit trail on purpose.
                    Risk::query()->whereKey($risk->id)->update(['review_reminded_for' => new Expression('next_review_on')]);
                });
            });

        return $sent;
    }
}
