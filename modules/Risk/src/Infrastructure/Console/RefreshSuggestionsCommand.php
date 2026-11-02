<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Suggestions\RefreshSuggestions;

#[Signature('risk:suggestions:refresh')]
#[Description('Refresh the risks suggested by the other modules, for every organization')]
final class RefreshSuggestionsCommand extends Command
{
    public function handle(RefreshSuggestions $refresh, OrganizationContext $context): int
    {
        $opened = 0;

        foreach (Organization::query()->orderBy('id')->get(['id']) as $organization) {
            $context->runAs($organization->id, function () use ($refresh, &$opened): void {
                $opened += $refresh->handle()['opened'];
            });
        }

        $this->components->info($opened.' new suggestion(s).');

        return self::SUCCESS;
    }
}
