<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Risk\Application\Reminders\SendRiskDigest;

#[Signature('risk:digest')]
#[Description('Email risk managers what the register needs')]
final class SendRiskDigestCommand extends Command
{
    public function handle(SendRiskDigest $digest): int
    {
        $this->components->info($digest->handle().' organization(s) emailed.');

        return self::SUCCESS;
    }
}
