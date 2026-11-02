<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Risk\Application\Reminders\SendReviewReminders;

#[Signature('risk:reviews:remind')]
#[Description('Email risk owners whose risks are due for reassessment')]
final class SendReviewRemindersCommand extends Command
{
    public function handle(SendReviewReminders $reminders): int
    {
        $this->components->info($reminders->handle().' risk reminder(s) sent.');

        return self::SUCCESS;
    }
}
