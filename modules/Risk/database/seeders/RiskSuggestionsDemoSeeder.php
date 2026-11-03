<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Suggestions\RefreshSuggestions;

/**
 * Runs last: the risk suggestions the other modules' demo data gives rise
 * to (unapproved BIA, open major nonconformities...), as the risk manager
 * finds them. The signal listener is queued, so it is not relied on here.
 */
final class RiskSuggestionsDemoSeeder extends Seeder
{
    public function run(DemoCompany $company, OrganizationContext $context, RefreshSuggestions $suggestions): void
    {
        $context->runAs($company->organization, fn (): array => $suggestions->handle());
    }
}
