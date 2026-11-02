<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Projects\Application\Actions\RemindReports;

#[Signature('projects:report-reminders')]
#[Description('Email project managers the projects whose status report is due')]
final class RemindReportsCommand extends Command
{
    public function handle(RemindReports $remind): int
    {
        $this->components->info($remind->handle().' manager(s) emailed.');

        return self::SUCCESS;
    }
}
