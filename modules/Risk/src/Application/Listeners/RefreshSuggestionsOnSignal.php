<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Domain\Events\RiskSignalsChanged;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Suggestions\RefreshSuggestions;

/**
 * Refreshes an organization's suggestions as soon as a module says its view
 * changed (e.g. an application's documentation came into force), after the
 * change is committed.
 */
final class RefreshSuggestionsOnSignal implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly RefreshSuggestions $refresh,
        private readonly OrganizationContext $context,
    ) {}

    public function handle(RiskSignalsChanged $event): void
    {
        $this->context->runAs($event->organizationId, fn () => $this->refresh->handle());
    }
}
