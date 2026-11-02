<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Bia\Application\Reviews\SendReviewReminders;

#[Signature('bia:reviews:remind')]
#[Description('Email process owners whose impact analysis is due for review')]
final class SendReviewRemindersCommand extends Command
{
    public function handle(SendReviewReminders $reminders): int
    {
        $this->components->info($reminders->handle().' review reminder(s) sent.');

        return self::SUCCESS;
    }
}
