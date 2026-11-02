<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Bcm\Application\Reminders\SendContinuityDigest;

#[Signature('bcm:digest')]
#[Description('Email continuity coordinators what their plans need')]
final class SendContinuityDigestCommand extends Command
{
    public function handle(SendContinuityDigest $digest): int
    {
        $this->components->info($digest->handle().' organization(s) emailed.');

        return self::SUCCESS;
    }
}
