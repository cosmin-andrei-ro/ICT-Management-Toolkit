<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Ticketing\Application\Actions\AutoCloseResolvedTickets;

#[Signature('ticketing:tickets:auto-close')]
#[Description('Close tickets left resolved for the configured grace period')]
final class AutoCloseTicketsCommand extends Command
{
    public function handle(AutoCloseResolvedTickets $autoClose): int
    {
        $this->components->info($autoClose->handle().' ticket(s) closed.');

        return self::SUCCESS;
    }
}
