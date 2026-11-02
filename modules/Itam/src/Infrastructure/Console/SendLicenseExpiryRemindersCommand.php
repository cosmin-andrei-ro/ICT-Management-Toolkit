<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Itam\Application\Licenses\SendLicenseExpiryReminders;

#[Signature('itam:licenses:remind')]
#[Description('Email licence managers about licences expiring soon')]
final class SendLicenseExpiryRemindersCommand extends Command
{
    public function handle(SendLicenseExpiryReminders $reminders): int
    {
        $this->components->info($reminders->handle().' licence expiry(ies) announced.');

        return self::SUCCESS;
    }
}
