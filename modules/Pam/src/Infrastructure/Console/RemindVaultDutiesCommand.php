<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Pam\Application\Actions\RemindVaultDuties;

#[Signature('pam:reminders')]
#[Description('Email who should rotate a privileged credential or review who has access')]
final class RemindVaultDutiesCommand extends Command
{
    public function handle(RemindVaultDuties $remind): int
    {
        $this->components->info($remind->handle().' reminder(s) sent.');

        return self::SUCCESS;
    }
}
