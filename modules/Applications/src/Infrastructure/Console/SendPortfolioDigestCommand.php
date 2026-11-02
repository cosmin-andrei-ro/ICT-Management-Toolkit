<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Infrastructure\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Applications\Application\Reminders\SendPortfolioDigest;

#[Signature('applications:digest')]
#[Description('Email application managers what the portfolio needs')]
final class SendPortfolioDigestCommand extends Command
{
    public function handle(SendPortfolioDigest $digest): int
    {
        $this->components->info($digest->handle().' organization(s) emailed.');

        return self::SUCCESS;
    }
}
