<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Itam\Application\Warranty\SendWarrantyReminders;

#[Signature('itam:warranties:remind')]
#[Description('Email asset managers about warranties ending soon')]
final class SendWarrantyRemindersCommand extends Command
{
    public function handle(SendWarrantyReminders $reminders): int
    {
        $this->components->info($reminders->handle().' warranty end(s) announced.');

        return self::SUCCESS;
    }
}
