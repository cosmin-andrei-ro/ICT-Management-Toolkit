<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Application\Contracts\SendContractReminders;

#[Signature('core:contracts:remind')]
#[Description('Renew contracts that renew by themselves and email reminders before contract deadlines')]
final class SendContractRemindersCommand extends Command
{
    public function handle(SendContractReminders $reminders): int
    {
        $this->components->info($reminders->handle().' contract reminder(s) sent.');

        return self::SUCCESS;
    }
}
