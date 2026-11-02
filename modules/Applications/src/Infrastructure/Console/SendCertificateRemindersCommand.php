<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Applications\Application\Reminders\SendCertificateReminders;

#[Signature('applications:certificates:remind')]
#[Description('Email application owners about certificates expiring soon')]
final class SendCertificateRemindersCommand extends Command
{
    public function handle(SendCertificateReminders $reminders): int
    {
        $this->components->info($reminders->handle().' certificate reminder(s) sent.');

        return self::SUCCESS;
    }
}
