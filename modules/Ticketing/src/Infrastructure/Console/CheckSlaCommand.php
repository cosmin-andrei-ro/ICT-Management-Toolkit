<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Ticketing\Application\Sla\CheckSlaDeadlines;

#[Signature('ticketing:sla:check')]
#[Description('Record SLA warnings and breaches whose time has come, and notify')]
final class CheckSlaCommand extends Command
{
    public function handle(CheckSlaDeadlines $check): int
    {
        $this->components->info($check->handle().' SLA clock(s) updated.');

        return self::SUCCESS;
    }
}
