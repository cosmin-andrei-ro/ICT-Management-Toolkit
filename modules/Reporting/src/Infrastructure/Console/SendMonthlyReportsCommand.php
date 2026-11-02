<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Reporting\Application\Actions\SendMonthlyReports;

#[Signature('reporting:monthly-report')]
#[Description('Email the management report of the month that ended to its subscribers')]
final class SendMonthlyReportsCommand extends Command
{
    public function handle(SendMonthlyReports $reports): int
    {
        $this->components->info($reports->handle().' subscriber(s) emailed.');

        return self::SUCCESS;
    }
}
