<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Audit\Application\Reminders\SendTestReminders;

#[Signature('audit:tests:remind')]
#[Description('Email control owners whose controls are due for their periodic test')]
final class SendTestRemindersCommand extends Command
{
    public function handle(SendTestReminders $reminders): int
    {
        $this->components->info($reminders->handle().' test reminder(s) sent.');

        return self::SUCCESS;
    }
}
