<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Sla;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Domain\Models\SlaTimer;

/**
 * Finds running clocks past their warning or due time, across organizations.
 * Both queries use partial indexes on running clocks only.
 */
final readonly class CheckSlaDeadlines
{
    public function __construct(
        private SlaClock $clock,
        private OrganizationContext $context,
    ) {}

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $checked = 0;

        SlaTimer::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('running_since')
            ->where(fn ($query) => $query
                ->where(fn ($warn) => $warn->whereNull('warned_at')->where('warn_at', '<=', $now))
                ->orWhere(fn ($due) => $due->whereNull('breached_at')->where('due_at', '<=', $now)))
            ->orderBy('id')
            ->each(function (SlaTimer $timer) use (&$checked): void {
                $this->context->runAs($timer->organization_id, fn () => $this->clock->checkDeadline($timer));
                $checked++;
            });

        return $checked;
    }
}
