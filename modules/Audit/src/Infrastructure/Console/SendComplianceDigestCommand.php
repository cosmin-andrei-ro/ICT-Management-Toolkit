<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Audit\Application\Reminders\SendComplianceDigest;

#[Signature('audit:digest')]
#[Description('Email compliance managers what compliance needs')]
final class SendComplianceDigestCommand extends Command
{
    public function handle(SendComplianceDigest $digest): int
    {
        $this->components->info($digest->handle().' organization(s) emailed.');

        return self::SUCCESS;
    }
}
