<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Applications\Application\Reminders\SendReviewReminders;

#[Signature('applications:reviews:remind')]
#[Description('Email application owners whose documentation is due for review')]
final class SendReviewRemindersCommand extends Command
{
    public function handle(SendReviewReminders $reminders): int
    {
        $this->components->info($reminders->handle().' review reminder(s) sent.');

        return self::SUCCESS;
    }
}
