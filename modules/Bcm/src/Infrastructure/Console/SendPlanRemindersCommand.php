<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Bcm\Application\Reminders\SendPlanReminders;

#[Signature('bcm:plans:remind')]
#[Description('Email plan owners whose plan is due for review or for an exercise')]
final class SendPlanRemindersCommand extends Command
{
    public function handle(SendPlanReminders $reminders): int
    {
        $sent = $reminders->handle();
        $this->components->info($sent['reviews'].' review and '.$sent['exercises'].' exercise reminder(s) sent.');

        return self::SUCCESS;
    }
}
